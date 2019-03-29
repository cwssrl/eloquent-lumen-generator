CHANGELOG

2019-03-22, v1.8.6
-----------------
* Changed make:resource to use stub

2019-03-22, v1.8.5
-----------------
* Added logical search in url queries

2019-03-22, v1.8.4
-----------------
* Updated base date format in base model

2019-03-21, v1.8.3
-----------------
* Fixed missing method in repo

2019-03-20, v1.8.2
-----------------
* Fixed query for json array with no nesting level in eloquent repo

2017-07-19, v1.2.6
-----------------
* Add Laravel 5.5 support

2017-07-19, v1.2.5
-----------------
* Add custom primary key, key type and incrementing properties support

2017-06-23, v1.2.4
-----------------
* Fixed issue [#25](https://github.com/Cws/eloquent-model-generator/issues/25)

2016-08-20, v1.2.0
-----------------
* Add table prefix support
* Fixed relation's related table generation
* Fixed type hinting for relation's methods php doc
* Fixed ManyToMany relation's arguments generation
* Performed internal refactoring

2016-07-16, v1.1.0
-----------------
* Added possibility to register custom database types (like `enum`)
* Added possibility to define configs at `config/eloquent_model_generator.php`
* Removed `--config` (`-c`) argument from `GenerateModelCommand`'s signature

2016-04-03, v1.0.0
-----------------
* Initial Version