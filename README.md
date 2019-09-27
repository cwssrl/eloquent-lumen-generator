# Eloquent Lumen Generator

Eloquent Lumen Generator is a tool based on [Code Generator](https://github.com/cwssrl/code-generator) for generating Eloquent models.
It comes with the possibility to generate the following items:
- Model;
- Repository and Contracts, binding them into the bootstrap/app.php file;
- Api Controller;
- Routes;
- Resources;
- Translation management for tables.

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
php artisan cws:generate Book --all
```
to generate all you need for the Book class. Generator will look for table with name `books` and generate the model, the repo, the contract, the controller, the routes for it.

You can also generate data for all your tables at once
```
php artisan cws:generate all --all
```

## The docs are not finished, I will write them as soon as possible