# TYPO3 Core Patches

[![Packagist Version](https://img.shields.io/packagist/v/gilbertsoft/typo3-core-patches)](https://packagist.org/packages/gilbertsoft/typo3-core-patches)
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/gilbertsoft/typo3-core-patches)](https://packagist.org/packages/gilbertsoft/typo3-core-patches)
[![Packagist Downloads](https://img.shields.io/packagist/dt/gilbertsoft/typo3-core-patches)](https://packagist.org/packages/gilbertsoft/typo3-core-patches)
[![GitHub issues](https://img.shields.io/github/issues/GsTYPO3/core-patches)](https://github.com/GsTYPO3/core-patches/issues)
[![GitHub forks](https://img.shields.io/github/forks/GsTYPO3/core-patches)](https://github.com/GsTYPO3/core-patches/network)
[![GitHub stars](https://img.shields.io/github/stars/GsTYPO3/core-patches)](https://github.com/GsTYPO3/core-patches/stargazers)
[![GitHub license](https://img.shields.io/github/license/GsTYPO3/core-patches)](https://github.com/GsTYPO3/core-patches/blob/main/LICENSE)
[![GitHub build](https://img.shields.io/github/actions/workflow/status/GsTYPO3/core-patches/continuous-integration.yml?branch=main)](https://github.com/GsTYPO3/core-patches/actions/workflows/continuous-integration.yml)
[![Coveralls](https://img.shields.io/coveralls/github/GsTYPO3/core-patches)](https://coveralls.io/github/GsTYPO3/core-patches)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg)](https://github.com/GsTYPO3/core-patches/blob/main/CODE_OF_CONDUCT.md)

This package let you easily manage TYPO3 Core patches with Composer based on
[cweagans/composer-patches](https://github.com/cweagans/composer-patches#readme)
which is a dependency of this package and requires to be installed as non-dev
dependency for production usage.

**Table of contents**:

- [Installation](#installation)
- [Usage](#usage)
  - [Adding a change](#adding-a-change)
  - [Updating a change](#updating-a-change)
  - [Removing a change](#removing-a-change)
  - [Supported change ID formats](#supported-change-id-formats)
- [Detection of merged changes on update or install](#detection-of-merged-changes-on-update-or-install)
- [Feedback / Bug reports / Contribution](#feedback--bug-reports--contribution)
- [License](#license)

## Installation

This package requires Composer 2.0 or higher.

Require this package as normal dependency:

```bash
composer require gilbertsoft/typo3-core-patches
```

If the package is installed as dev requirement, the patches won't get applied
using the install option `--no-dev`.

For Composer 2.2 and later, plug-ins must be explicitly allowed using the
following command:

```bash
composer config allow-plugins.gilbertsoft/typo3-core-patches true
```

`cweagans/composer-patches` is automatically added by this plugin.

## Usage

### Adding a change

Lookup the change ID at <https://forge.typo3.org> or <https://review.typo3.org>
and provide it as argument or multiple arguments for multiple changes at once:

```bash
composer typo3:patch:apply 12345
composer typo3:patch:apply 12345 23456 34567
```

This plugin will then properly create patch files for the change and save it to
the patch directory which defaults to `patches`. The patch directory can be
changed by the option `--patch-dir` or with the shortcut `-p`:

```bash
composer typo3:patch:apply --patch-dir:path/to/folder 12345
composer typo3:patch:apply -ppath/to/folder 12345
```

By default changes in tests are exluded. If you also like to include these
changes, provide the option `--tests` or the shortcut `-t`. This will result in
installing the sources instead of the dist packages for the affected packages.

```bash
composer typo3:patch:apply --tests 12345
composer typo3:patch:apply -t 12345
```

### Updating a change

To update the applied patches to the last patch sets from Gerrit just run the
following command, which will update all patches:

```bash
composer typo3:patch:update
```

It is also possible to just update some single patches by providing the change
ID as argument or multiple arguments for multiple changes to update at once:

```bash
composer typo3:patch:update 12345
composer typo3:patch:update 12345 23456 34567
```

### Removing a change

Provide the change ID to remove as argument or multiple arguments for multiple
changes to remove at once:

```bash
composer typo3:patch:remove 12345
composer typo3:patch:remove 12345 23456 34567
```

### Supported change ID formats

This plugin supports various change-id formats, as described in
<https://review.typo3.org/Documentation/rest-api-changes.html#change-id>.

Additionally, you can also specify the full URL for the change, as shown in the
next example:

```bash
composer typo3:patch:apply https://review.typo3.org/c/Packages/TYPO3.CMS/+/12345
```

## Verification of the branch

The plugin compares the current installed core version with the target branch
of the patch to install and asks for confirmation to anyway try to apply the
patch to the different version.

To disabled the branch check for this project, run:

```bash
composer config extra.gilbertsoft/typo3-core-patches.ignore-branch true
```

## Detection of merged changes on update or install

When running `composer update` or `composer install`, the plugin detects changes
that already exist in the version being installed and suggests removing them. If
you run Composer with the `--no-interaction` option, the patches are always
preserved. This can be changed by the config `force-tidy-patches` see bellow.

Errors may occur if you use the source-dist of packages, which can be solved by
adding the `config.discard-changes` configuration option to your `composer.json`,
see <https://getcomposer.org/doc/06-config.md#discard-changes>. Run e.g.
`composer config discard-changes true` to add the configuration to your
`composer.json`.

If a CI environment is detected, the detection of merged changes is skipped by
default. To change this behavior and enable the detection again, run:

```bash
composer config extra.gilbertsoft/typo3-core-patches.force-tidy-patches true
```

To disable the detection of merged changes completely, run:

```bash
composer config extra.gilbertsoft/typo3-core-patches.disable-tidy-patches true
```

## CI detection

The plugin tries to detect CI environments and changes its default behavior
while running in a CI pipeline. It's possible to override the detection by
setting an environment variable:

- Set `GS_CI=1` to force CI mode
- Set `GS_CI=0` to disable CI mode

## Feedback / Bug reports / Contribution

Bug reports, feature requests and pull requests are welcome in the [GitHub
repository](https://github.com/GsTYPO3/core-patches).

For support questions or other discussions please use the [GitHub Discussions](https://github.com/GsTYPO3/core-patches/discussions)
or join the dedicated [TYPO3 Slack channel](https://typo3.slack.com/archives/C03GY4LEVPU).

## License

This package is licensed under the [MIT License](https://github.com/GsTYPO3/core-patches/blob/main/LICENSE).
