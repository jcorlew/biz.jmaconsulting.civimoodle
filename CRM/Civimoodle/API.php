<?php

/**
 * Class to send Moodle API request
 */
class CRM_Civimoodle_API {

  /**
   * Instance of this object.
   *
   * @var CRM_CiviMoodle_API
   */
  public static $_singleton = NULL;

  /**
   * Search parameters later formated into API url arguments
   *
   * @var array
   */
  protected $_searchParams;

  /**
   * Instance of CRM_Utils_HttpClient
   *
   * @var CRM_Utils_HttpClient
   */
  protected $_httpClient;

  /**
   * Variable to store Moodle web access token
   *
   * @var string
   */
  protected $_wsToken;

  /**
   * Variable to store Moodle web domain
   *
   * @var string
   */
  protected $_domain;

  /**
   * The constructor sets search parameters and instantiate CRM_Utils_HttpClient
   */
  public function __construct($searchParams = array()) {
    $this->_searchParams = $searchParams;
    $this->_httpClient = new CRM_Utils_HttpClient();
    $this->_wsToken = Civi::settings()->get('moodle_access_token');
    $this->_domain = Civi::settings()->get('moodle_domain');
  }

  /**
   * Singleton function used to manage this object.
   *
   * @param array $searchParams
   *   Moodle parameters
   *
   * @return CRM_CiviMoodle_API
   */
  public static function &singleton($searchParams = array(), $reset = FALSE) {
    if (self::$_singleton === NULL || $reset) {
      self::$_singleton = new CRM_CiviMoodle_API($searchParams);
    }
    return self::$_singleton;
  }

  /**
   * Function to call core_user_get_users webservice to fetch moodle user
   */
  public function getUser() {
    return $this->sendRequest('core_user_get_users');
  }

  /**
   * Function to core_user_create_users webservice to create moodle user
   */
  public function createUser() {
    return $this->sendRequest('core_user_create_users');
  }

  /**
   * Function to core_user_create_users webservice to create moodle user
   */
  public function updateUser() {
    return $this->sendRequest('core_user_update_users');
  }

  /**
   * Function to core_course_get_courses webservice to create moodle user
   */
  public function getCourses() {
    return $this->sendRequest('core_course_get_courses');
  }

  /**
   * Function to enrol_manual_enrol_users webservice to enroll moodle user for given course
   */
  public function enrollUser() {
    return $this->sendRequest('enrol_manual_enrol_users');
  }

  public function suspendEnrolment() {
    return $this->sendRequest('enrol_manual_enrol_users');
  }

  public function unenrollUser() {
    return $this->sendRequest('enrol_manual_unenrol_users');
  }

  /**
   * Function used to make Moodle API request
   *
   * @param string $apiFunc
   *   Donor Search API function name
   *
   * @return array
   */
  public function sendRequest($apiFunc) {
    $searchArgs = array(
      'wstoken=' . $this->_wsToken,
      'wsfunction=' . $apiFunc,
      'moodlewsrestformat=json',
    );

    switch ($apiFunc) {
      case 'core_user_get_users':
        // expects search params to be in array('key' => 'firstname', 'value' => 'Adam') format
        foreach (array('key', 'value') as $arg) {
          $searchArgs[] = "criteria[0][$arg]=" . $this->_searchParams[$arg];
        }
        break;

      case 'core_user_create_users':
        foreach (array('username', 'createpassword', 'firstname', 'lastname', 'email') as $arg) {
          $searchArgs[] = "users[0][$arg]=" . $this->_searchParams[$arg];
        }
        break;

      case 'core_user_update_users':
        foreach (array('id', 'firstname', 'lastname', 'email') as $arg) {
          if (!empty($this->_searchParams[$arg])) {
            $searchArgs[] = "users[0][$arg]=" . $this->_searchParams[$arg];
          }
        }
        break;

      case 'enrol_manual_enrol_users':
        foreach (array('roleid', 'userid', 'courseid') as $arg) {
          $searchArgs[] = "enrolments[0][$arg]=" . $this->_searchParams[$arg];
        }
        //if we are editing participant records, we need to suspend or activate moodle enrolments
        if ($this->_searchParams['suspend']) {
          $searchArgs[] = "enrolments[0][suspend]=" . $this->_searchParams['suspend'];
        }
        break;

      case 'enrol_manual_unenrol_users':
        foreach (array('roleid', 'userid', 'courseid') as $arg) {
          $searchArgs[] = "enrolments[0][$arg]=" . $this->_searchParams[$arg];
        }
        break;

      default:
        //do nothing
        break;
    }

    // send API request with desired search arguments
    $url = sprintf("%swebservice/rest/server.php?%s",
      CRM_Utils_File::addTrailingSlash($this->_domain, '/'),
      str_replace(' ', '+', implode('&', $searchArgs))
    );
    list($status, $response) = $this->_httpClient->get($url);
    return array(
      self::recordError($response),
      $response,
    );
  }

  /**
   * Record error response if there's anything wrong in $response
   *
   * @param string $response
   *   fetched data from Moodle API
   *
   * @return bool
   *   Found error ? TRUE or FALSE
   */
  public static function recordError($response) {
    $isError = FALSE;
    $response = json_decode($response, TRUE);

    if (!empty($response['exception'])) {
      civicrm_api3('SystemLog', 'create', array(
        'level' => 'error',
        'message' => $response['message'],
        'contact_id' => CRM_Core_Session::getLoggedInContactID(),
      ));
      $isError = TRUE;
    }

    return $isError;
  }

}
