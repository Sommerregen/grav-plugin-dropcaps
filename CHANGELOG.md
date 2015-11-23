# v1.3.4
## 11/23/2015

3. [](#bugfix)
  * Fixed [#2](https://github.com/Sommerregen/grav-plugin-dropcaps/issues/2) (PHP errors) [#3](https://github.com/Sommerregen/grav-plugin-dropcaps/pull/3)

# v1.3.3
## 09/08/2015

2. [](#improved)
  * Changed initialization procedure

# v1.3.2
## 09/08/2015

3. [](#bugfix)
  * Fixed broken `CHANGELOG.md`

# v1.3.1
## 09/07/2015

2. [](#improved)
  * Added blueprints for Grav Admin plugin
3. [](#bugfix)
  * Fixed [#1](https://github.com/Sommerregen/grav-plugin-dropcaps/issues/1) (Undefined property: `Grav\Plugin\DropCapsPlugin::$backend`)

# v1.3.0
## 08/08/2015

1. [](#new)
  * Added admin configurations **(requires Grav 0.9.34+)**
2. [](#improved)
  * Switched to `onBuildPagesInitialized` event **(requires Grav 0.9.29+)**
  * Updated `README.md`

# v1.2.1
## 05/10/2015

2. [](#improved)
  * PSR fixes

# v1.2.0
## 02/07/2015

2. [](#improved)
  * Improved process engine to ensure not to alter HTML tags or HTML entities in content
  * Refactored code
3. [](#bugfix)
  * Fixed self-closing tags and ensure to return valid HTML(5)

# v1.1.0
## 02/07/2015

1. [](#new)
  * Completely new re-design of DropCaps style
2. [](#improved)
  * Added support for HHVM **(requires Grav 0.9.17+)**
  * Added modular pages support
  * Improved readability of code
  * Updated plugin to use new `mergeConfig` method of Grav core **(requires Grav 0.9.16+)**

# v1.0.1
## 01/27/2015

1. [](#new)
	* Added option `process` to toggle `DropCaps` filter per page
2. [](#bugfix)
	* Fixed issue with unexpected behavior of `dropcaps` per-page configuration

# v1.0.0
## 01/26/2015

1. [](#new)
    * ChangeLog started...
