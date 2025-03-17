# OPSONE - Drupal - Liste de dépendances

## Introduction
Ce module Drupal sert à lister toutes les informations sur les dépendences utilisées par un projet Drupal.

Pour accéder au JSON de sortie, il faut aller à `adresseRacineDuProjet/stalker/dependencies` depuis l'une des adresses ip autorisée.

## Prérequis
- Avoir installé le module sur le site
- Avoir activé le module dans les paramètres Drupal
- Avoir ajouté la ligne de configuration au fichier `settings.php` du projet

## Installation
- Ouvrir le fichier `composer.json` du projet
- Ajouter l'information suivante dans le tableau `repositories`
```json
{
    "type": "vcs",
    "url": "https://github.com/opsone/stalker-drupal"
}
```
ou celle-ci, si `repositories`est un objet nommé
```json
    "opsone": {
        "type": "vcs",
        "url": "https://github.com/opsone/stalker-drupal"
    }
```
- Éxécuter la commande `composer require opsone/stalker_module`
- Activer le module dans les paramètres Drupal
- Ajouter la ligne de configuration au fichier `settings.php` du projet

### Ligne de configuration à ajouter au fichier `settings.php`
```php
$settings['ops_server_monitoring_ip'] = ['ip1', 'ip2', ...];
```
#### **<span style="color: #dc3545">Attention : S'il n'y a qu'une adresse ip qui est authorisée, veillez bien à la renseigner dans un tableau</span>**

### Si les binaires n'utilisent pas le chemin par défaut
- Ajouter les lignes de configuration suivante au fichier `settings.php` du projet
```php
$settings['ops_php_bin'] = '/path/to/php';
$settings['ops_node_bin'] = '/path/to/node';
$settings['ops_npm_bin'] = '/path/to/npm';
$settings['ops_composer_bin'] = '/path/to/composer';
$settings['ops_elastic_search_api_url'] = 'elasticsearch_api_url';
```
#### Chaque ligne de configuration est indépendente l'une de l'autre, si elle n'est pas renseignée, le chemin par défaut sera utilisé.

### Si une URI spécifique doit être précisé
- Ajouter la ligne de configuration suivante au fichier `settings.php` du projet
```php
$settings['ops_uri'] = 'uri-of-the-project';
```

## Mettre à jour le module
- Exécuter la commande suivante en locale avant de déployer
```sh
composer update opsone/stalker_module
```