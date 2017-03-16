# 1.3.14

* [gfml-29] Fixed order of country fields in translated pages.

# 1.3.13

## Fixes
* [gfml-87] Fixed GF date on TM dashboard
* [gfml-75] Fix issue where radio choices with long text and html were not being displayed.

# 1.3.12

## Fixes
* [gfml-76] The "Save and Continue Later" button can be properly translated.
* [gfml-78] Fixed a fatal error occurring when registering strings with unicode characters.

## Performances
* [gfml-81] Removed the `GFML_TM_Legacy_API` class since the plugin requires at least WPML 3.5.0.

# 1.3.11

## Fixes
* [gfml-79] Fixed bug with post category field registration.
* Other minor bug fixes

# 1.3.10

## Fixes
* [wpmlcore-3030] Class auto loading is not compatible with version of PHP older than 5.3

## Performances
* [wpmlga-133] Improved class autoloading by using class mapping, instead of file system lookup

# 1.3.9

## Features
* [wpmltm-1189] Implement new design for translation editor.

## Performances
* [wpmlcore-2988] Removed unneeded dependencies checks in admin pages: this now runs only once and later only when activating/deactivating plugins

# 1.3.8

## Fix
* [gfml-63] Fixed logic to limit string length
* [gfml-57] Fixed issue when a field was removed, the related strings were not removed
* [gfml-51] Previous and next buttons are now fully translatable

# 1.3.7

## Performances
* [wpmlcore-2528] Cached calls to `glob()` function when auto loading classes

## Compatibility
* [gfml-33] Added compatibility with Quiz add-on (http://www.gravityforms.com/add-ons/quiz/)

# 1.3.5
* [gfml-58] Fix migration logic so string statuses are copied correctly

# 1.3.4

## Features
* [gfml-43] Added ability to change the language of a form

## Fixes
* [gfml-56] Page titles strings in paginated forms are now properly registered

# 1.3.3

## Fixes
* Is now possible to translate all "choices" options (labels and values) of fields with this property

# 1.3.1

## New
* Updated dependency check module

# 1.3.1

## New
* Updated dependency check module

# 1.3.0

* Added support for Package Translation
* Improved translation when default language is not English

# 1.2.2

* Added support for GF 1.9.x

# 1.2.1

* Fixed translating HTML content field type
* Fixed translating option labels for radio, checkbox and select field types
* Fixed issue with broken HTML and JS on Translation Editor screen when label contains HTML content
* Fixed issues with Price Fields string registration and translation
* Added filtering on WPML Translation Management Dashboard screen


# 1.2.0

* Fixed problem with translations of deleted fields
* Fixed small issue with "Translation of this document is complete" checkbox (should not be checked in some cases)

# 1.0.0

* Add readme.txt
* Add filter to translate error messages
* Translate page titles in multipage forms
* Translate previous, next and last button texts (and button imageUrls) in multipage forms
* Translate multiselect values, also in merge tags
* Translate price labels for products/options
* For choice fields (dropdowns, etc), translate the option label, not the actual value (needed for conditional logic to work)
* Translate multiple confirmations. Confirmations are translated for messages and for page and url redirections
* Translate multiple notifications (emails). They are translated when then email To field is entered by the user, and sent to the user in the languageinwhich the form was submitted
* Merge tags work correctly with translations
* gform_pre_render now takes two arguments, and handles confirmations differently
* Remove gform_confirmation filter, as it works in a different way in GF 1.7
* Add actions for updating forms and form settings (confirmations and notifications). Changed original content (e.g. field labels) appears immediately in the Translation Editor with 'translation is finished' unchecked. Addition and deletion of fields is also handled correctly, without having to delete the translation job and resubmit a new one
* Translation status is correctly reflected in the Translation Dashboard and the Translation Queue
* Add action for form duplication from the Translation Dashboard. The duplicate is then available as a translation of the form that can be edited with the Translation Editor
* Add action for form deletion
* Gravity forms appear in Translation Dashboard with the 'Any' filter
* Add our own filters 'gform_multilingual_form_keys' and 'gform_multilingual_field_keys' so that plugin authors can register additional keys for translation
* Display warning when WPML or Gravity Forms are inactive and do not load plugin
