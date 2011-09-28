<?php
	// Engine
	define('ENGINE_VERSION',			"1.0.2");

	// Locations
	define('MAIN_LOCATION',				"/home/585.ru/www/");

	// Site
	define('SITE_CHARSET',				"utf-8");
	define('SITE_DATETIME_FORMAT',			"d.m.Y H:i");
	define('SITE_BASE_PATH',			"Страницы");
	define('SITE_DEFAULT_TEMPLATE',			"Шаблоны/Страница");
	define('SITE_DEFAULT_PAGE',			"Главная");

	// Database
	define('DATABASE_HOST',				"localhost");
	define('DATABASE_NAME',				"585_ru");
	define('DATABASE_USER',				"585.ru");
	define('DATABASE_PASSWORD',			"VbhBHDusl");
	define('DATABASE_PREFIX',			"itw_");

	// Captcha
	define('CAPTCHA_LENGTH',			5);
	define('CAPTCHA_FONT_LOCATION',			MAIN_LOCATION."tahoma.ttf");

	// Session
	define('SESSION_NAME',				"585ID");
	define('SESSION_DOMAIN_NAME',			"585.ru");
	define('SESSION_LIFE_TIME',			365 * 24 * 60 * 60);
	define('SESSION_MAX_LOGIN_LEN',			32);
	define('SESSION_MAX_PASSWORD_LEN',		128);

	// Accounts
	define('ACCOUNTS_SESSION_PREFIX',		"ACCOUNTS");
	define('ACCOUNTS_PASSWORD_SALT',		"ef33a1d");
	define('ACCOUNTS_PASSWORD_LENGTH',		32);

	// News
	define('NEWS_BASE_PATH',			"Страницы/Новости");
	define('NEWS_LAST_COUNT',			10);
	define('NEWS_DIRECTORY',			"news/");
	define('NEWS_IMAGE_DIRECTORY',			"images/");
	define('NEWS_FILES_DIRECTORY',			"files/");
	define('NEWS_IMAGE_NAME',			"image.jpg");
	define('NEWS_IMAGE_WIDTH',			200);
	define('NEWS_IMAGE_HEIGHT',			200);

	// Pages
	define('PAGES_VERSIONS_PER_PAGE',		50);

	// Admin
	define('ADMIN_SESSION_PREFIX',			"ADMIN");
	define('ADMIN_PASSWORD_SALT',			"3fdso3");

	// Cache
	define('CACHE_HOST',				"localhost");
	define('CACHE_PORT',				11211);
	define('CACHE_HASH_PREFIX',			SESSION_DOMAIN_NAME);

	// Mail
	define('MAIL_CHARSET',				"utf-8");
	define('MAIL_SUPPORT',				"inna@585.ru");

	// Products
	define('PRODUCTS_PER_PAGE',			24);
	define('PRODUCTS_CACHE_TIME',			60 * 60);
	define('PRODUCTS_NEWS_CACHE_TIME',		60 * 60);
	define('PRODUCTS_FILES_DIRECTORY',		"products/");
	define('PRODUCTS_NEWS_SHORT_COUNT',		15);
	define('PRODUCTS_IMPORT_DIR_MAGIC',		"import/magic_gold/");
	define('PRODUCTS_IMPORT_DIR_GOLD_STANDARD',	"import/goldStandardImages/");
	define('PRODUCTS_IMPORT_DIR_ADAMAS',		"import/adamas/");
	define('PRODUCTS_IMPORT_DIR_HOTDIAMODS',	"import/hotdiamods/");
	define('PRODUCTS_RESULT_DIR_MAGIC',		"import/result_magic_gold/");
	define('PRODUCTS_RESULT_DIR_GOLD_STANDARD',	"import/result_gold_standard/");
	define('PRODUCTS_RESULT_HOTDIAMONDS',		"import/result_hotdiamonds/");
	define('PRODUCTS_RESULT_DIR_ADAMAS',		"import/adamas_result/");

	// Files
	define('FILES_MAX_NAME_LENGTH',			128);
	define('FILES_MAX_SIZE',			999999999999);
	define('FILES_TEMP_PICTURE',			MAIN_LOCATION."img/import/temp.jpg");

	// Images
	define('IMAGES_QUALITY',			80);
	define('IMAGES_DIRECTORY',			"img/");
	define('IMAGE_NAME_BIG',			"big");
	define('IMAGE_NAME_SMALL',			"small");
	define('IMAGES_SMALL_WIDTH',			170);
	define('IMAGES_SMALL_HEIGHT',			160);
	define('IMAGES_BIG_WIDTH',			387);
	define('IMAGES_BIG_HEIGHT',			233);
?>