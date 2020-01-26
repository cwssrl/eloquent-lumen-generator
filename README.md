# Eloquent Lumen Generator

[![SonarCloud](https://sonarcloud.io/images/project_badges/sonarcloud-white.svg)](https://sonarcloud.io/dashboard?id=cwssrl_eloquent-lumen-generator)  [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=cwssrl_eloquent-lumen-generator&metric=alert_status)](https://sonarcloud.io/dashboard?id=cwssrl_eloquent-lumen-generator)  [![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=cwssrl_eloquent-lumen-generator&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=cwssrl_eloquent-lumen-generator)

Eloquent Lumen Generator is a tool based on [Code Generator](https://github.com/cwssrl/code-generator) for generating Eloquent models.
It comes with the possibility to generate the following items:
- Model;
- Repository and Contracts, binding them into the bootstrap/app.php file;
- Api Controller;
- Routes;
- Resources;
- Translation management for tables.
It manages MySql and PostgreSql as database. 

## Requirements

To make this package works you have to make your migrations and run them. All the package will create items based on 
the table that it can find on the database set on your .env file.

## Installation
Step 1. Add Eloquent Lumen Generator to your project:
```
composer require cwssrl/eloquent-lumen-generator --dev
```
Step 2. Register `GeneratorServiceProvider` in bootstrap/app.php file:
```php
$app->register(Cws\EloquentModelGenerator\Provider\GeneratorServiceProvider::class);
```

Step 3. Uncomment AppServiceProvider in bootstrap/app.php file: 

Step 4 (optional). If you want to edit package configurations, you have to copy vendor/cwssrl/eloquent-lumen-generator/src/Resources/eloquent_model_generator.php into your config folder. 

## Usage
Use
```
php artisan cws:generate Book --all-api
```
to generate all you need for the Book class. Generator will look for table with name `books` and generate the model, the repo, the contract, the controller, the routes for it.

You can also generate data for all your tables at once
```
php artisan cws:generate all --all-api
```

### Valid options

You can use these options as command parameters

Option | Description
--- | ---
table-name | Set the table name generator has to use generating your model (e.g. "cws:generate Test --table-name=hello" will use table named "hello" to generate model "Test"). It can be used only when one model a time is created.
output-path | Set the path name generator has to use saving your model relative to app folder (e.g. "cws:generate Test --output-path=hello" will save your model "Test" in app/hello folder).
namespace | Set the namespace name generator has to use generating your model (e.g. "cws:generate Test --namespace=hello" will use namespace "hello" to generate model "Test").
base-class-name | Set which class will extends your model (e.g. "cws:generate Test --base-class-name=hello" will extends "hello" class generating "Test").
no-timestamps | Set timestamps property to false
date-format | dateFormat property
connection | connection property
except-tables | Tables to not process separated by ","
api-resource | Creates api resource too
repository | Creates repository too
api-controller | Creates api controller too
api-routes | Creates api routes too
api-routes-path | Set the path where find api route file (default api.php)
all-api | Create all objects related to api, models and repos included 


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Credits

- [Samuele Salvatico](https://www.linkedin.com/in/samuele-salvatico-89527464/)
- [Andrea Romanello](https://www.linkedin.com/in/andrea-romanello/)

This package is heavily based on [Cws Code Generator](https://github.com/cwssrl/code-generator) that is a fork of the [krlove/code-generator](https://github.com/krlove/code-generator) package

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
