# suprb-cms-builder

## INSTALL 
**So easy to install!** Install globally with composer:
```bash
composer require "ametsuramet/suprb-cms-builder"
```

## PUBLISH CMS JSON FILE

```php
php artisan vendor:publish --provider=Suprb\CmsGenerator\CmsGeneratorServiceProvider --tag=cmsbuilder-json --force
``` 

## USE
Generate CMS
```php
php artisan cms:install
```
