<?php

namespace Drupal\stalker_module\dependencies\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Site\Settings;


class DependenciesController extends ControllerBase
{
  public function content()
  {

    // Get the access token from settings.php
    $token = Settings::get('ops_stalker_token', false);

    // Check the token if it's enabled
    if ($token && !(isset($_GET['token']) && hash_equals($token, $_GET['token']))) {
      return new Response('Access denied', 401);
    }

    // Get root path of the project
    $root_path = DRUPAL_ROOT;
    $url_elements = explode('/', $root_path);
    $web_folder = false;

    if (end($url_elements) == 'web') {
      $root_path .= '/..';
      $web_folder = true;
    }

    $php_bin = "export HOME='$root_path' && ".Settings::get('ops_php_bin', 'php');
    $node_bin = Settings::get('ops_node_bin', 'node');
    $npm_bin = Settings::get('ops_npm_bin', 'npm');
    $composer_bin = $php_bin.' '.Settings::get('ops_composer_bin', 'composer');
    $uri = Settings::get('ops_uri', false);

    $elastic_search_api_url = Settings::get('ops_elastic_search_api_url', 'http://localhost:9200');


    // Get active theme path
    $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
    if ($web_folder) {
      $theme_path = "web/$theme_path";
    }


    // Get node version
    $node_version = shell_exec("cd $root_path/$theme_path/; $node_bin -v");
    preg_match('/\d+\.\d+\.\d+/', $node_version, $node_version_formatted);

    $npm_version = shell_exec("cd $root_path/$theme_path/; $npm_bin -v");
    preg_match('/\d+\.\d+\.\d+/', $npm_version, $npm_version_formatted);

    // Get drupal version
    $drush_status = "$php_bin $root_path/vendor/bin/drush status --format=json";
    if ($uri) $drush_status.= " --uri=\"$uri\"";

    $drupal_dependencies = [];

    $drush_status_result = $this->getData($drush_status);
    if (!empty($drush_status_result)) {
      $drupal_version = $drush_status_result->{'drupal-version'};
      $drupal_dependencies[] = array(
        'name' => 'drupal',
        'dep_type' => 'drupal',
        'version' => $drupal_version
      );
    }

    // Get drupal modules
    $drush_pm_list = "$php_bin $root_path/vendor/bin/drush pm-list --status=Enabled --type=Module --no-core --format=json";
    if ($uri) $drush_pm_list.= " --uri=\"$uri\"";

    $drupal_modules = $this->getData($drush_pm_list);
    if (!empty($drupal_modules)) {
      foreach ($drupal_modules as $module => $mod_info) {
        $drupal_dependencies[] = array(
          'name' => $module,
          'dep_type' => 'drupal_module',
          'version' => $mod_info->version ?? 'unknown'
        );
      }
    }

    $composer_dependencies = null;

    $deploy_path = "$root_path/../../.dep";
    if (file_exists("$deploy_path/composer.phar")) {
      $composer_cmd = "$php_bin $deploy_path/composer.phar";
      $composer_dependencies = $this->getComposerInformations($root_path, $composer_cmd);
    } else {
      exec("$composer_bin --version", $output, $return_var);
      if ($return_var == 0) {
        $composer_dependencies = $this->getComposerInformations($root_path, $composer_bin);
      }
    }

    // Get npm dependencies
    $npm_package = $this->getData("cat $root_path/$theme_path/package.json");
    $npm_informations = shell_exec("cd $root_path/$theme_path/; $npm_bin list --depth=0");

    $npm_dependencies = [];
    if (isset($npm_package, $npm_informations)) {

      $npm_wanted_dependencies = isset($npm_package->dependencies) ? get_object_vars($npm_package->dependencies)  : [];
      if (isset($npm_package->devDependencies)) {
        $npm_wanted_dependencies = array_merge($npm_wanted_dependencies, get_object_vars($npm_package->devDependencies));
      }

      $npm_informations_lines = explode("\n", $npm_informations);
      foreach ($npm_wanted_dependencies as $dep => $version) {
        foreach ($npm_informations_lines as $line) {
          if (preg_match('/^(├──|└──) (.+)@([\d.]+)$/', trim($line), $matches)) {

            $name = $matches[2];
            $version = $matches[3];
            if ($name == $dep) {
              $npm_dependencies[] = array(
                'name' => $name,
                'dep_type' => 'node_module',
                'version' => $version
              );
              break;
            }
          }
        }
      }
    }

    // Get OS version
    $os_informations = null;
    $debian_version = shell_exec('cat /etc/debian_version');
    if (!empty($debian_version)) {
      $os_informations = array(
        'name' => 'debian',
        'dep_type' => 'os',
        'version' => trim($debian_version) ?? 'unknown'
      );
    } else {
      $os_release = shell_exec('cat /etc/os-release');

      if (!empty($os_release)) {
        $os_release_exploded = explode("\n", $os_release);
        $os_release_parsed = array();

        foreach ($os_release_exploded as $line) {
          if (empty($line)) {
            continue;
          }
          list($key, $value) = explode('=', $line, 2);
          $os_release_parsed[$key] = trim($value, '"');
        }
      }

      if (isset($os_release_parsed)) {
        $os_informations = array(
          'name' => $os_release_parsed['ID'],
          'dep_type' => 'os',
          'version' => $os_release_parsed['VERSION_ID'] ?? 'unknown'
        );
      }
    }

    // Get elasticsearch version if exist
    $elastic_options = [
      "http" => [
          "method" => "GET",
          "header" => "Content-Type: application/json\r\n"
      ]
    ];
    $elastic_context = stream_context_create($elastic_options);
    $elastic_result = @file_get_contents($elastic_search_api_url, false, $elastic_context);

    $list = [
      array(
        'name' => 'php',
        'dep_type' => 'php',
        'version' => phpversion()
      ),
      array(
        'name' => 'node',
        'dep_type' => 'node',
        'version' => $node_version_formatted[0] ?? 'unknown'
      ),
      array(
        'name' => 'npm',
        'dep_type' => 'npm',
        'version' => $npm_version_formatted[0] ?? 'unknown'
      )
    ];

    if ($os_informations != null) {
      array_push($list, $os_informations);
    }

    if ($drupal_dependencies != null) {
      $list = array_merge($list, $drupal_dependencies);
    }

    if ($npm_dependencies != null) {
      $list = array_merge($list, $npm_dependencies);
    }

    if ($composer_dependencies != null) {
      $list = array_merge($list, $composer_dependencies);
    }

    if ($elastic_result) {
      $elastic_result = json_decode($elastic_result);

      $elastic_version = [array(
        'name' => 'elasticsearch',
        'dep_type' => 'search_server',
        'version' => $elastic_result->version->number
      )];

      $list = array_merge($list, $elastic_version);
    }

    $response = new Response(json_encode($list));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

  // Get composer dependencies
  private function getComposerInformations($root_path, $composer_cmd)
  {
    // Get composer informations
    $composer_informations = shell_exec("cd $root_path/; $composer_cmd show -D --format=json");
    $composer_dependencies = [];

    if ($composer_informations != null) {
      // Get composer version
      $composer_version = shell_exec("cd $root_path/; $composer_cmd --version");
      preg_match('/\d+\.\d+\.\d+/', $composer_version, $composer_version_formatted);

      $composer_dependencies[] = array(
        'name' => 'composer',
        'dep_type' => 'composer',
        'version' => $composer_version_formatted[0] ?? 'unknown'
      );

      $composer_informations_decoded = json_decode($composer_informations);
      foreach ($composer_informations_decoded->installed as $dep) {

        // Skip drupal dependencies
        if (substr($dep->name, 0, 7) == 'drupal/') {
          continue;
        }

        $composer_dependencies[] = array(
          'name' => $dep->name,
          'dep_type' => 'composer_vendor',
          'version' => $dep->version ?? 'unknown'
        );
      }
      return $composer_dependencies;
    } else {
      return null;
    }
  }

  private function getData($command) {
    $result = shell_exec($command);
    $jsonResult = null;
    if (!empty($result)) {
      $jsonResult = json_decode($result);
    }
    return $jsonResult;
  }
}
