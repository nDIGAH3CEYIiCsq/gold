/*
Copyright (c) 2003-2010, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.editorConfig = function( config )
{
	config.language = "ru";
	config.ignoreEmptyParagraph = true;
	config.image_removeLinkByEmptyURL = true;
	config.resize_enabled = false;
	config.shiftEnterMode = CKEDITOR.ENTER_BR;
	config.startupMode = "source";
	config.toolbarStartupExpanded = true;
	config.tabSpaces = 1;
	config.contentsCss = "/css/user/style.css";
};