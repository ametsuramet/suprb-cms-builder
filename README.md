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

## EDIT JSON FILE
edit _cmsbuilder.json_ 
```json
[
	{
		"name": "Book",
		"softdelete": false,
		"primaryKey": null,
		"resource": true,
		"relations": [
			{"type": "belongs_to", "target":"Category"}
		],
		"schema": [
			{
				"field": "title",
				"type": "string",
				"nullable": true,
				"searchable": true,
				"default": "NULL",
				"form_type": "text",
				"options": []
			},
			{
				"field": "description",
				"type": "text",
				"nullable": true,
				"searchable": true,
				"default": "NULL",
				"form_type": "textarea",
				"options": []
			},
			{
				"field": "picture",
				"type": "string",
				"nullable": true,
				"searchable": false,
				"default": "NULL",
				"form_type": "file",
				"options": []
			},
			{
				"field": "author_id",
				"type": "integer:unsigned",
				"nullable": true,
				"searchable": false,
				"default": "NULL",
				"form_type": "select",
				"options": [
					{"value": 1, "label": "user 1"},
					{"value": 2, "label": "user 2"}
				]
			},
			{
				"field": "category_id",
				"type": "integer:unsigned",
				"nullable": true,
				"searchable": false,
				"default": "NULL",
				"form_type": "select",
				"options": []
			},
			{
				"field": "publish",
				"type": "boolean",
				"nullable": true,
				"searchable": false,
				"default": "true",
				"form_type": "radio",
				"options": [
					{"value": 1, "label": "option 1"},
					{"value": 2, "label": "option 2"}
				]
			}
		]
	}
]
```

## USE
Generate CMS
```php
php artisan cms:install
```

### TODO

- [x] MIGRATION
- [x] MODEL
- [x] VIEW
- [x] CONTROLLER
- [x] ROUTE
- [x] REQUEST
- [x] PERMISSION
- [x] JWT-Support
- [ ] Swagger-Support
- [ ] Faker
- [ ] Socialite
- [ ] Update Feature
