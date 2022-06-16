<?php

namespace Drupal\discourse_membership\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Syncs a group membership to Discourse.
 *
 * @Action(
 *   id = "discourse_sync_membership",
 *   label = @Translation("Sync group membership"),
 *   type = "group_content"
 * )
 */
class SyncMembershipAction extends EntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\group\Entity\GroupContentInterface $entity */
    discourse_membership_sync_membership($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\group\Entity\GroupContentInterface $object */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
