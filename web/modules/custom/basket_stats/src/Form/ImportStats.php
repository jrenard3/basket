<?php

namespace Drupal\basket_stats\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Make convocation for a match
 */
class ImportStats extends FormBase
{
    
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'match_convocation';
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $match = null)
    {
        $matchDatas = Node::load($match);
        
        $form['titre_match'] = array(
            '#markup' => '<h1>'.t('Import des statistiques'). ' ' .$matchDatas->getTitle().'</h1>'
        );
        
        $form['stats'] = array(
            '#type' => 'file',
            '#title' => t('Statistiques'),
        );
        
        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Importer')
        );
        
        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $matchDatas->id()])->toString();
        $form['cancel'] = array(
            '#markup' => '<a href="'.$url.'">'.t('Annuler').'</a>'
        );
        
        $form['match_id'] = array(
            '#type' => 'hidden',
            '#value' => $match
        );
        
        return $form;
    }
    
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $files = $_FILES['files'];
        
        require_once DRUPAL_ROOT . '/../vendor/autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($files['tmp_name']['stats']);
    
        $pages  = $pdf->getPages();
        $matchDatas = [];
        foreach ($pages as $p) {
            $pageText = $p->getText();
            $tab = explode("\n", $pageText);
            
            for ($a = 3; $a < count($tab); $a++) {
                $datas = explode("\t", $tab[$a]);

                if (isset($datas[4]) && $datas[4] !== '') {
                    $matchDatas[$datas[1]][] = [
                        'equipe' => $datas[3],
                        'infos' => $datas[4],
                        'temps' => $datas[2],
                    ];
                }
            }
        }
    
        // Récupération de la correspondance des joueurs
        $matchNode = Node::load($values['match_id']);
        $equipe = ($matchNode->field_domicile_exterieur->value == 'domicile') ? 'A' : 'B';
        $equipeAutre = ($equipe === 'A') ? 'B' : 'A';
        
        $joueursConvoques = [];
        $a = 0;
        while (null !== $jDatas = $matchNode->field_joueurs_convoques->get($a)) {
            $j = $jDatas->getValue();
            $joueur = Node::load($j['target_id']);
    
            $joueursConvoques[$joueur->id()] = [
                'id' => $joueur->id(),
                'nom' => trim($joueur->field_joueur_nom->value),
                'prenom' => trim($joueur->field_joueur_prenom->value),
                'url' => $joueur->url(),
                'numero' => '',
                'ref' => ''
            ];
            $a++;
        }
        
        // On match les joueurs convoqués avec la feuille de match
        $refJoueurs = [];
        $joueursAssoc = [];
        $cinqMajeur = [];
        foreach ($matchDatas['Avant match'] as $d => $l) {
            if ($equipe == $l['equipe']) {
                $e = explode(',', $l['infos']);
                if (preg_match('/ajout/', $l['infos'])) {
                    foreach ($joueursConvoques as $id => $jd) {
                        if (preg_match('/'.$jd['nom'].'/', $e[1]) && $equipe == $e[0][0]) {
                            $joueursConvoques[$id]['ref'] = $e[0];
                            $joueursConvoques[$id]['numero'] = str_replace($equipe, '', $e[0]);
                            $joueursAssoc[$e[0]] = $id;
                            $refJoueurs[$e[0]] = trim($e[1]);
                        }
                    }
                }
    
                if (preg_match('/entré sur le terrain/', $l['infos'])) {
                    $cinqMajeur[] = $joueursAssoc[$e[0]];
                }
            } else {
                $i = explode(',', $l['infos']);
                if (!isset($joueursAdverses[$i[0]]) && $i[0][0] == $equipeAutre) {
                    $joueursAdverses[$i[0]] = [
                        'id' => $i[0],
                        'ref' => $i[0],
                        'nom' => trim($i[1]),
                        'numero' => str_replace($equipeAutre, '', $i[0]),
                    ];
                }
            }
        }
        
        // On parcours ensuite les périodes du matchs pour
        $statistiques = [];
        for ($a = 1; $a <= 4; $a++) {
            $key = 'Période '.$a;
            foreach ($matchDatas[$key] as $k => $d) {
                $e = explode(',', $d['infos']);
                
                // Analyse des infos pour connaitre le type d'évènement
                $evt = $this->getEvenementInformations($e);
                
                if (!$evt) {
                } else {
                    $refJoueur = isset($joueursAssoc[$evt['ref']])
                        ? $joueursAssoc[$evt['ref']]
                        : $joueursAdverses[$evt['ref']]['id'];
                    
                    $statistiques['Q'.$a][] = [
                        'joueur' => $refJoueur,
                        'type' => $evt['type'],
                        'temps' => $d['temps'],
                        'equipe' => isset($joueursAssoc[$evt['ref']]) ? $equipe : $equipeAutre,
                    ];
                }
            }
        }
        
        // Enregistrement des statistiques du match
        $stats = [
            'equipeClub' => $equipe,
            '5_majeur' => $cinqMajeur,
            'statistiques' => $statistiques,
            'joueurs' => $joueursConvoques,
            'joueursAdverses' => $joueursAdverses,
            'equipeClub' => $equipe,
        ];

        $matchNode->set('field_match_statistiques', serialize($stats));
        $matchNode->save();
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $matchNode = Node::load($values['match_id']);
        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $matchNode->id()]);
        
        $form_state->setRedirectUrl($url);
    }
    
    public function getEvenementInformations($evt)
    {
        $evtPreg = array(
            'Tir à 2 points réussi' => 'PT_2',
            'Tir à 3 points réussi' => 'PT_3',
            'sorti du terrain' => 'CH_OUT',
            'entré sur le terrain' => 'CH_IN',
            'Faute' => 'FAUTE',
            'Lancer franc manqué' => 'LF_KO',
            'Lancer franc réussi' => 'LF_OK',
            'Temps-Mort' => 'TM',
        );
    
        $ret = false;
        
        foreach ($evtPreg as $k => $e) {
            if ((isset($evt[1]) && preg_match('/'.$k.'/', $evt[1])) || preg_match('/'.$k.'/', $evt[0])) {
                if ($e == 'FAUTE') {
                    $s = explode(' ', $evt[0]);
                    $ref = $s[count($s) - 1];
                } else {
                    $ref = $evt[0];
                }
                
                $ret = [
                    'type' => $e,
                    'ref' => $ref,
                ];
                
                break;
            }
        }
        return $ret;
    }
}
