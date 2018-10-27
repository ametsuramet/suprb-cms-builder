# suprb-cms-builder

## INSTALL 
**So easy to install!** Install globally with composer:
```bash
composer require "ametsuramet/suprb-cms-builder:dev-master"
```

## PUBLISH CMS JSON FILE

```php
php artisan vendor:publish --tag=cmsbuilder-json --force
``` 

## USE
Generate CMS
```php
php artisan cms:install
```
