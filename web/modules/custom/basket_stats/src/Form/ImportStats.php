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
class ImportStats extends FormBase {
    
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'match_convocation';
    }
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $match = null) {
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
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $files = $_FILES['files'];
        
        require_once DRUPAL_ROOT . '/../vendor/autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($files['tmp_name']['stats']);
    
        $pages  = $pdf->getPages();
        $matchDatas = [];
        foreach($pages as $p) {
            $pageText = $p->getText();
            $tab = explode("\n", $pageText);
            
            for($a = 3; $a < count($tab); $a++) {
                $datas = explode("\t", $tab[$a]);
                $matchDatas[$datas[1]][] = [
                    'equipe' => $datas[3],
                    'infos' => $datas[4]
                ];
            }
        }
        
        // Récupération de la correspondance des joueurs
        $matchNode = Node::load($values['match_id']);
        $equipe = ($matchNode->field_domicile_exterieur->value == 'domicile') ? 'A' : 'B';
        
        $joueursConvoques = [];
        $a = 0;
        while(null !== $jDatas = $matchNode->field_joueurs_convoques->get($a)) {
            $j = $jDatas->getValue();
            $joueur = Node::load($j['target_id']);
    
            $joueursConvoques[] = [
                'id' => $joueur->id(),
                'nom' => $joueur->field_joueur_nom->value,
                'numero' => '',
                'ref' => ''
            ];
            $a++;
        }
        
        // On match les joueurs convoqués avec la feuille de match
        $refJoueurs = [];
        foreach($matchDatas['Avant match'] as $d => $l) {
            if ($equipe == $l['equipe']) {
                $e = explode(',', $l['infos']);
                if (preg_match('/ajout/', $l['infos'])) {
                    foreach($joueursConvoques as $id => $jd) {
                        if (preg_match('/'.$jd['nom'].'/', $e[1]) && $equipe == $e[0][0]) {
                            $joueursConvoques[$id]['ref'] = $e[0];
                            $joueursConvoques[$id]['numero'] = str_replace($equipe, '', $e[0]);
                        }
                    }
                    $refJoueurs[$e[0]] = $e[1];
                }
            }
        }
        
        // On parcours ensuite les périodes du matchs pour
        for($a = 1; $a <= 4; $a++) {
            $key = 'Période '.$a;
            foreach($matchDatas[$key] as $k => $d) {
                $e = explode(',', $d['infos']);
                echo "<pre>DEBUG " . __FILE__ . " - " . __LINE__ . " <br/>";
                var_dump($e);
                echo "</pre>";
            }
            die;
        }
        echo "<pre>DEBUG " . __FILE__ . " - " . __LINE__ . " <br/>";
        var_dump($joueursConvoques, $matchDatas);
        echo "</pre>";
        die;
        //$joueursMatch = $matchNode->field
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
    }
}