<?php

namespace Drupal\basket_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\wide_mytagheuer\Helper\MyTagSSO;

/**
 * Provides a menu for mytag.
 *
 * @Block(
 *   id = "categorie_equipes_listing",
 *   admin_label = @Translation("Categorie Equipes Listing"),
 *   category = @Translation("Blocks")
 * )
 */
class CategorieEquipesListing extends BlockBase {
    
    /**
     * {@inheritdoc}
     */
    public function build() {
        $taxonomie = \Drupal::request()->get('taxonomy_term');
        $currentRoute = \Drupal::service('current_route_match')->getRouteName();

        if ('entity.taxonomy_term.canonical' === $currentRoute
            && null !== $taxonomie
            && 'categories' === $taxonomie->bundle()) {
            $taxonomie_id = $taxonomie->id();
            
            // Récupération des équipes liées à cette catégorie d'age
            $equipes = _basket_core_get_equipes($taxonomie_id);
    
            // Pour chaque équipe, on récupère les derniers matchs
            if (!empty($equipes)) {
                foreach($equipes as $k => $nid) {
                    $equipe = Node::load($nid);
                    $matchs = _basket_core_get_equipes_matchs($nid);
                    $block_match = array(
                        '#theme' => 'match',
                        '#dernier_resultat' => $matchs['dernier_resultat'],
                        '#prochain_match' => $matchs['prochain_match'],
                    );
                    $equipeDatas[] = array(
                        'nom' => $equipe->getTitle(),
                        'url' => $equipe->url(),
                        'matchs' => \Drupal::service('renderer')->render($block_match),
                    );
                }
            }
    
            return array(
                '#theme' => 'equipes',
                '#equipes' => $equipeDatas
            );
        }
    }
}
