<?php

/*
 * Drush script to reconcile Drupal-side Discourse username field with data from
 * the upstream Discourse app.
 *
 * While paging through the discourse user listing, update corresponding
 * discourse usernames based on matching discourse user id.
 *
 * Mismatches between Drupal username and Discourse name are logged for
 * informational purposes only, but not updated.
 */

/** @var \Drupal\discourse\DiscourseApiClient $discourse_client */
$discourse_client = \Drupal::service('discourse.discourse_api_client');
$page = 1;
while ($discourse_data = $discourse_client->getUsers($page)) {
  $discourse_cleaned = [];
  if (!$discourse_data) {
    break;
  }
  $discourse_data = json_decode($discourse_data);
  if (empty($discourse_data)) {
    break;
  }
  foreach ($discourse_data as $user) {
    $discourse_cleaned[$user->id] = $user;
  }
  $page++;

  $query = \Drupal::entityTypeManager()->getStorage('user')->getQuery();
  $query->condition('discourse_user_field__user_id', array_keys($discourse_cleaned), 'IN');
  $query->exists('discourse_user_field__push_to_discourse');
  $query->exists('discourse_user_field__username');
  $results = $query->execute();

  foreach ($results as $uid) {
    $account = \Drupal\user\Entity\User::load($uid);
    if (empty($account)) {
      \Drupal::logger('discourse')
        ->notice('Skip empty account :uid', [':uid' => $uid]);
      continue;
    }
    $discourse_id = $account->discourse_user_field->user_id;
    if (empty($discourse_cleaned[$discourse_id])) {
      \Drupal::logger('discourse')
        ->notice('No discourse match found for user :uid username from discourse :username', [
          ':uid' => $account->id(),
          ':username' => $account->getAccountName()
        ]);
      continue;
    }

    if ($account->getAccountName() != $discourse_cleaned[$discourse_id]->username) {
      \Drupal::logger('discourse')
        ->warning('Discourse username does not match drupal not match :uid :username :disco', [
          ':uid' => $account->id(),
          ':username' => $account->getAccountName(),
          ':disco' => $discourse_cleaned[$discourse_id]->username
        ]);
      continue;
    }

    if ($account->discourse_user_field->username == $discourse_cleaned[$discourse_id]->username) {
      \Drupal::logger('discourse')
        ->notice('Discourse name already match for user :uid username from discourse :username', [
          ':uid' => $account->id(),
          ':username' => $account->getAccountName()
        ]);
      continue;
    }
    $account->discourse_user_field->username = $discourse_cleaned[$discourse_id]->username;
    $account->save();
    \Drupal::logger('discourse')
      ->notice('Updated user :uid disco username from discourse :username', [
        ':uid' => $account->id(),
        ':username' => $account->getAccountName()
      ]);

  }
}