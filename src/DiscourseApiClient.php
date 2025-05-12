<?php

namespace Drupal\discourse;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Utility\Error;
use Drupal\user\Entity\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

/**
 * Discourse API functionality.
 *
 * @package Drupal\discourse
 */
class DiscourseApiClient {

  /**
   * HTTP client factory.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Api headers.
   *
   * @var array|\Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig|null
   */
  private $apiHeaders;

  /**
   * Base url for discourse.
   *
   * @var array|\Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig|null
   */
  private $baseUrl;

  /**
   * Cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * CatFactsClient constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   Http client factory.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   Current user service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   *   Current user service.
   */
  public function __construct(ClientFactory $http_client_factory, ConfigFactory $config_factory, CacheBackendInterface $cacheBackend, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, Connection $database, AccountProxy $current_user, LoggerChannelFactory $logger_factory) {
    $discourseSettings = $config_factory->get('discourse.discourse_settings');
    $this->loggerFactory = $logger_factory;
    try {
      $this->baseUrl = $discourseSettings->get('base_url_of_discourse');
      $this->apiHeaders = [
        'Api-Key' => $discourseSettings->get('api_key'),
        'Api-Username' => $discourseSettings->get('api_user_name'),
      ];

      $this->client = $http_client_factory->fromOptions([
        'base_uri' => $this->baseUrl,
        'timeout' => 30,
      ]);
      $this->cache = $cacheBackend;
      $this->time = $time;
      $this->configFactory = $discourseSettings;
      $this->entityTypeManager = $entity_type_manager;
      $this->database = $database;
      $this->currentUser = $current_user;
    }
    catch (ConnectException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
  }

  /**
   * Get topic by topic id.
   *
   * @param int $topic_id
   *   Topic id.
   *
   * @return string|bool
   *   Returns topic data.
   */
  public function getTopic(int $topic_id) {
    $uri = sprintf('/t/%s.json', $topic_id);
    try {
      $response = $this->client->get($uri, [
        'headers' => $this->apiHeaders,
      ]);

      return $response->getBody()->getContents();
    }
    catch (ConnectException | ClientException | RequestException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }

    return FALSE;
  }

  /**
   * Get list of categories from discourse.
   *
   * @return \Psr\Http\Message\StreamInterface|bool
   *   Returns list of categories from discourse.
   */
  public function getCategories() {
    if ($categories = $this->cache->get('discourse_category')) {
      return $categories->data;
    }
    $uri = sprintf('/categories.json');
    try {
      $response = $this->client->get($uri, [
        'headers' => $this->apiHeaders,
      ]);

      $data = Json::decode($response->getBody());
      $time_value = $this->time->getCurrentTime();
      // 12 hours cache time for categories data.
      $this->cache->set('discourse_category', $data, $time_value + 43200);
      return $data;
    }
    catch (ConnectException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

  /**
   * Get list of categories from discourse.
   *
   * @return \Psr\Http\Message\StreamInterface|bool
   *   Returns list of categories from discourse.
   */
  public function getCurrentUserCategories() {
    $current_user = User::load($this->currentUser->getAccount()->id());
    $discourse_username = $current_user->get('discourse_user_field')->username;

    if ($discourse_username) {
      $uri = sprintf('/categories.json');
      $headers = $this->apiHeaders;
      $headers['Api-Username'] = $discourse_username;

      try {
        $response = $this->client->get($uri, [
          'headers' => $headers,
        ]);

        $data = Json::decode($response->getBody());
        // $time_value = $this->time->getCurrentTime();
        // 12 hours cache time for categories data.
        // $this->cache->set('discourse_category', $data, $time_value + 43200);
        return $data;
      }
      catch (ConnectException | GuzzleException $e) {
        Error::logException($this->loggerFactory->get('discourse'), $e);
      }
    }
    return FALSE;
  }

  /**
   * Post topic to discourse.
   *
   * @param array $data
   *   Post data.
   *
   * @return string
   *   Returns newly created post data.
   */
  public function postTopic(array $data) {
    $headers = $this->apiHeaders;
    $headers['Content-Type'] = 'multipart/form-data';
    $headers['Accept'] = 'application/json; charset=utf-8';
    $headers['content-encoding'] = 'gzip';
    $uri = '/posts.json';
    try {
      $response = $this->client->post($uri, [
        'form_params' => $data,
        'headers' => $headers,
      ]);
      return $response->getBody()->getContents();
    }
    catch (ConnectException|GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
  }

  /**
   * Returns base url.
   *
   * @return array|\Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig|mixed|null
   *   Base url for discourse.
   */
  public function getBaseUrl() {
    return $this->baseUrl;
  }

  /**
   * Get list of latest comments from discourse.
   *
   * @param int $count
   *   Number of comments to display.
   * @param int $before
   *   Get older posts before post id.
   * @param array $latest_comments_data
   *   Latest comments array. Used in subsequent request if less then 5
   *   comments present for corresponding node.
   * @param int $pass
   *   Number of time discourse api is called for getting latest posts.
   *
   * @return array
   *   Returns latest comments from discourse.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLatestComments($count = 5, $before = 0, array $latest_comments_data = [], $pass = 1) {
    if ($latest_comments = $this->cache->get('discourse_latest_comments')) {
      return $latest_comments->data;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get node id from topic id.
   *
   * @param int $topic_id
   *   Topic id from discourse.
   *
   * @return array|int
   *   Nid corresponding to topic id.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeFromTopicId($topic_id) {
    $query = $this->database->select('node_field_data', 'nf');
    $query->addField('nf', 'nid');
    $query->condition('nf.discourse_comments_field__topic_id', $topic_id);
    $results = $query->execute();
    $results = $results->fetch();
    $nid = 0;
    if (isset($results->nid)) {
      $nid = $results->nid;
    }

    if ($nid) {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $node = $node_storage->load($nid);
      return $node;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get default avatar image path.
   *
   * @return string
   *   Returns default avatar image path.
   */
  public function getDefaultAvatar() {
    return sprintf('%s/%s', \Drupal::service('extension.list.module')->getPath('discourse'), 'images/user-default.png');
  }

  /**
   * Get header for discourse api client.
   *
   * @return array|\Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig|null
   *   Returns api headers for discourse client.
   */
  public function getHeaders() {
    return $this->apiHeaders;
  }

  /**
   * Get the client for sending api requests.
   *
   * @return \GuzzleHttp\Client
   *   Returns the client for api requests.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Get topic ids which has disscourse comment count.
   */
  public function getTopicIdsWithComments() {
    $query = $this->database->select('node_field_data', 'nf');
    $query->addField('nf', 'discourse_comments_field__topic_id');
    $query->condition('nf.discourse_comments_field__comment_count', 0, '>');
    $query->condition('nf.status', 1);
    $query->orderBy('created', 'DESC');
    $query->range(0, 20);
    $results = $query->execute();
    $results = $results->fetchAll();
    $topic_ids = [];
    foreach ($results as $record) {
      $topic_ids[] = $record->discourse_comments_field__topic_id;
    }

    if (count($topic_ids) > 0) {
      return $topic_ids;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Fetch latest comments from discourse and set cache.
   *
   * @param int $count
   *   Number of comments to fetch.
   *
   * @return bool|false|object
   *   Returns latest comments data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fetchLatestComments($count = 5) {
    try {
      // Get top 20 topic ids for checking latest comments.
      $topic_ids = $this->getTopicIdsWithComments();

      $all_comments = [];
      // Generate array for all comments.
      foreach ($topic_ids as $topic) {
        if (is_numeric($topic)) {
          $d_topic = Json::decode($this->getTopic($topic));
          if (isset($d_topic['post_stream']) && isset($d_topic['post_stream']['posts'])) {
            foreach ($d_topic['post_stream']['posts'] as $key => $comment) {
              // Forst entry is topic so skip it, we just want comments.
              if ($key > 0) {
                // Skip if user have deleted the comment.
                if ($comment['user_deleted']) {
                  continue;
                }
                $comment_time = strtotime($comment['created_at']);

                $all_comments[$comment_time]['id'] = $comment['id'];
                $all_comments[$comment_time]['username'] = $comment['username'];
                $all_comments[$comment_time]['topic_id'] = $comment['topic_id'];
                $all_comments[$comment_time]['user_deleted'] = $comment['user_deleted'];
                $all_comments[$comment_time]['avatar_template'] = $comment['avatar_template'];
                $all_comments[$comment_time]['post_content'] = Unicode::truncate(strip_tags($comment['cooked']), 100, TRUE, TRUE, 10);
                $all_comments[$comment_time]['created_at'] = strtotime($comment['created_at']);
              }
            }
          }
        }
      }
      // Sort comments so we can pick latest 5.
      krsort($all_comments, SORT_NUMERIC);
      // Loop and pick top 5 comments.
      foreach ($all_comments as $comment) {
        // Remove deleted comments.
        if ($comment['user_deleted']) {
          continue;
        }

        $default_avatar_image = $this->getDefaultAvatar();
        // Appending base url if https:// does not exist in image path.
        if (strpos($comment['avatar_template'], "https://") === FALSE) {
          $avatar_image = sprintf('%s%s', $this->getBaseUrl(), str_replace('{size}', '90', $comment['avatar_template']));
        }
        else {
          $avatar_image = str_replace('{size}', '90', $comment['avatar_template']);
        }
        // Placing default avatar image if avatar image does not exist.
        if (@getimagesize($avatar_image)) {
          $latest_comments[$comment['id']]['avatar_template'] = $avatar_image;
        }
        else {
          $latest_comments[$comment['id']]['avatar_template'] = $default_avatar_image;
        }

        $latest_comments[$comment['id']]['username'] = $comment['username'];

        // Set comment url.
        $comment_node = $this->getNodeFromTopicId($comment['topic_id']);
        $link = $comment_node->url();
        $latest_comments[$comment['id']]['comment_url'] = sprintf("%s#discourse-comment", $link);

        $latest_comments[$comment['id']]['post_content'] = $comment['post_content'];

        if (count($latest_comments) >= $count) {
          break;
        }
      }
      $time_value = $this->time->getCurrentTime();
      // Convert cache_lifetime in seconds.
      $cache_lifetime = $this->configFactory->get('cache_lifetime') * 60;
      // Set cache time for discourse latest comments.
      $this->cache->set('discourse_latest_comments', $latest_comments, $time_value + $cache_lifetime);
      return $latest_comments;
    }
    catch (ConnectException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

  /**
   * Get the list of users from Discourse.
   *
   * @param int $page
   *   For multiple pages of results, call repeatedly and increment value.
   *
   * @return bool|string
   *   JSON response or FALSE.
   */
  public function getUsers($page = 1, array $params = []) {
    $uri = '/admin/users/list/new.json?page=' . $page;
    if (!empty($params)) {
      $uri .= '&' . \http_build_query($params);
    }
    return $this->getRequest($uri);
  }

  /**
   * Creates a category.
   *
   * @param array $data
   *   Category data to pass to the API.
   *    - name (required)
   *    - color
   *    - text_color
   *    - description.
   *
   * @return string|bool
   *   JSON response or FALSE.
   */
  public function createCategory(array $data) {
    $uri = '/categories.json';
    return $this->postRequest($uri, $data);
  }

  /**
   * Get a category.
   *
   * @param int $category_id
   *   Category id.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function getCategory(int $category_id) {
    $uri = sprintf('/c/%s/show.json', $category_id);
    return $this->getRequest($uri);
  }

  /**
   * Delete a category.
   *
   * @param int $category_id
   *   Category id.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function deleteCategory(int $category_id) {
    $uri = sprintf('/categories/%s.json', $category_id);
    return $this->deleteRequest($uri);
  }

  /**
   * Update a Category.
   *
   * @param int $category_id
   *   Category id.
   * @param array $data
   *   Category data to pass to the API.
   *    - name
   *    - color
   *    - text_color
   *    - description.
   */
  public function updateCategory(int $category_id, array $data) {
    $uri = sprintf('/categories/%s.json', $category_id);
    return $this->putRequest($uri, $data);
  }

  /**
   * Create a Group.
   *
   * @param array $data
   *   Group data to pass to the API nested under key of group.
   *    'group' => ['name' => 'value',].
   *
   * @return string|bool
   *   JSON response or FALSE.
   */
  public function createGroup(array $data) {
    $uri = '/admin/groups.json';
    return $this->postRequest($uri, $data);
  }

  /**
   * Get a group.
   *
   * @param string $group_name
   *   The name of the group.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function getGroup(string $group_name) {
    $uri = sprintf('/groups/%s.json', $group_name);
    return $this->getRequest($uri);
  }

  /**
   * Delete a group.
   *
   * @param int $group_id
   *   Category id.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function deleteGroup(int $group_id) {
    $uri = sprintf('/admin/groups/%s.json', $group_id);
    return $this->deleteRequest($uri);
  }

  /**
   * Update a group.
   *
   * @param int $group_id
   *   Group id.
   * @param array $data
   *   Group data to pass to the API nested under key of group.
   *    'group' => ['name' => 'value',].
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function updateGroup(int $group_id, array $data) {
    $uri = sprintf('/groups/%s.json', $group_id);
    return $this->putRequest($uri, $data);
  }

  /**
   * Add users to a group.
   *
   * @param int $group_id
   *   Group id.
   * @param string $usernames
   *   Comma separated list of usernames to add to the group.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function addUsersToGroup(int $group_id, string $usernames) {
    $uri = sprintf('/groups/%s/members.json', $group_id);
    $data = [
      'usernames' => $usernames,
    ];
    return $this->putRequest($uri, $data);
  }

  /**
   * Remove users from a group.
   *
   * @param int $group_id
   *   Group id.
   * @param string $usernames
   *   Comma separated list of usernames to add to the group.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function removeUsersFromGroup(int $group_id, string $usernames) {
    $uri = sprintf('/groups/%s/members.json', $group_id);
    $data = [
      'usernames' => $usernames,
    ];
    return $this->deleteRequest($uri, $data);
  }

  /**
   * Create a user.
   *
   * @param array $data
   *   Array of data:
   *     - name - string (required)
   *     - email - string (required)
   *     - password - string (required)
   *     - username - string (required)
   *     - active - bool
   *     - approved - bool.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function createUser(array $data) {
    $uri = '/users.json';
    return $this->postRequest($uri, $data);
  }

  /**
   * Delete a user.
   *
   * @param string $user_id
   *   The user id.
   * @param array $data
   *   Array of data:
   *     - delete_posts - bool
   *     - block_email - bool
   *     - block_urls - bool
   *     - block_ip - bool.
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function deleteUser(string $user_id, array $data) {
    $uri = sprintf('/admin/users/%s.json', $user_id);
    return $this->deleteRequest($uri, $data);
  }

  /**
   * Update a user's name (Display name).
   *
   * @param string $username
   *   Discourse user id.
   * @param array $data
   *   Array formatted like:
   *     ['name' => 'name value'].
   *
   * @return string|bool
   *   Returns JSON response or FALSE.
   */
  public function updateUserName(string $username, array $data) {
    $uri = sprintf('/u/%s.json', $username);
    return $this->putRequest($uri, $data);
  }

  /**
   * Get Request.
   *
   * @param string $uri
   *   Request path.
   *
   * @return string|bool
   *   JSON or FALSE.
   */
  private function getRequest(string $uri) {
    try {
      $response = $this->client->get($uri, [
        'headers' => $this->apiHeaders,
      ]);
      return $response->getBody()->getContents();
    }
    catch (ConnectException | ClientException | RequestException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

  /**
   * Post Request (for creating new content).
   *
   * @param string $uri
   *   Request path.
   * @param array $data
   *   Data for the request.
   *
   * @return string|bool
   *   JSON or FALSE.
   */
  private function postRequest(string $uri, array $data) {
    $headers = $this->apiHeaders;
    $headers['Content-Type'] = 'multipart/form-data';
    $headers['Accept'] = 'application/json; charset=utf-8';
    $headers['content-encoding'] = 'gzip';

    try {
      $response = $this->client->post($uri, [
        'form_params' => $data,
        'headers' => $headers,
      ]);
      return $response->getBody()->getContents();
    }
    catch (ConnectException | ClientException | RequestException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

  /**
   * Put Request (for updating content).
   *
   * @param string $uri
   *   Request path.
   * @param array $data
   *   Data for the request.
   *
   * @return string|bool
   *   JSON or FALSE.
   */
  private function putRequest(string $uri, array $data) {
    try {
      $response = $this->client->put($uri, [
        'form_params' => $data,
        'headers' => $this->apiHeaders,
      ]);
      return $response->getBody()->getContents();
    }
    catch (ConnectException | ClientException | RequestException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

  /**
   * Delete Request.
   *
   * @param string $uri
   *   Request path.
   * @param array $data
   *   Data for the request.
   *
   * @return string|bool
   *   JSON or FALSE.
   */
  private function deleteRequest(string $uri, array $data = []) {
    try {
      $response = $this->client->delete($uri, [
        'form_params' => $data,
        'headers' => $this->apiHeaders,
      ]);
      return $response->getBody()->getContents();
    }
    catch (ConnectException | ClientException | RequestException | GuzzleException $e) {
      Error::logException($this->loggerFactory->get('discourse'), $e);
    }
    return FALSE;
  }

}
