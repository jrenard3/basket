<?php

namespace Drupal\basket_convocation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Make convocation for a match
 */
class MatchConvocationForm extends FormBase {
    
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
        $matchJoueursConvoques = $matchDatas->field_joueurs_convoques;
        
        // Récupération des joueurs déjà convoqués pour le match
        $dejaConvoques = [];
        if (null !== $matchJoueursConvoques && !empty($matchJoueursConvoques)) {
            $a = 0;
            while(null !== $dc = $matchJoueursConvoques->get($a)) {
                $infos = $dc;
                $dejaConvoques[] = (int)$infos->getProperties()['target_id']->getValue();
                $a++;
            }
        }
        
        $equipeDatas = $matchDatas->field_equipe_du_club->entity;
        $equipeJoueurs = $equipeDatas->field_equipe_joueurs;
        
        $options = [];
        foreach($equipeJoueurs as $joueur) {
            $joueurDatas = $joueur->entity;
            $joueurTitle = $joueurDatas->getTitle();
            $joueurId = $joueurDatas->id();

            $options[$joueurId] = $joueurTitle;
        }
        
        $form['titre_match'] = array(
            '#markup' => '<h1>'.$matchDatas->getTitle().'</h1>'
        );
        
        $form['joueurs'] = array(
            '#type' => 'checkboxes',
            '#title' => t('Liste des joueurs'),
            '#options' => $options,
            '#default_value' => $dejaConvoques,
        );
        
        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Envoyer les convocations')
        );
        
        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $matchDatas->id()])->toString();
        $form['cancel'] = array(
            '#markup' => '<a href="'.$url.'">'.t('Annuler').'</a>'
        );
        
        $form['match_id'] = array(
            '#type' => 'hidden',
            '#value' => $match
        );
        
        $form['joueur_deja_convoques'] = array(
            '#type' => 'hidden',
            '#value' => serialize($dejaConvoques)
        );
        
        return $form;
    }
    
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $matchDatas = Node::load($values['match_id']);
        
        $joueursConvoques = $values['joueurs'];
        $joueursDejaConvoques = $values['joueurs_deja_convoques'];
        $joueursDejaConvoques = (null !== $joueursDejaConvoques) ? unserialize($joueursDejaConvoques) : [];
        
        $listeMails = [];
        foreach($joueursConvoques as $j) {
            if (!in_array($j, $joueursDejaConvoques)) {
                $jDatas = Node::load($j);
                $emails = $jDatas->field_joueur_mail;
                
                if (null !== $emails) {
                    $a = 0;
                    while(null !== $mail = $emails->get($a)) {
                        if (null !== $mail) {
                            $mailDatas = $mail->getValue();
                            $listeMails[]  = $mailDatas['value'];
                        }
                        $a++;
                    }
                }
            }
        }
    
        if (!empty($listeMails)) {
            $uid = \Drupal::currentUser()->id();
            $user = User::load($uid);
            $from = $user->getEmail();
    
            $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $matchDatas->id()], ['absolute' => TRUE])->toString();
    
            $mailDatas = array(
                'match_title' => $matchDatas->getTitle(),
                'match_domext' => $matchDatas->field_domicile_exterieur->value,
                'match_date' => strtotime($matchDatas->field_match_date->value),
                'match_url' => $url,
                'user_name' => $user->getUsername(),
            );
    
            $mailManager = \Drupal::service('plugin.manager.mail');
            $mailManager->mail('basket_convocation', 'convocation', implode(',', $listeMails), 'fr', $mailDatas, $from);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $matchDatas = Node::load($values['match_id']);
        $joueursConvoques = $values['joueurs'];
    
        // Apres envoi des mails, on met à jour le contenu match pour associer les joueurs convoqués
        $matchDatas->set('field_joueurs_convoques', $joueursConvoques);
        $matchDatas->save();
        
        // Une fois la mise à jour du match réalisée, on retourne sur la fiche du match et on met un message de confirmation
        $form_state->setRedirect('entity.node.canonical', ['node' => $values['match_id']]);
    }
}