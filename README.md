# TYPO3 Core Patches

[![Continuous Integration (CI)](https://github.com/GsTYPO3/core-patches/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/GsTYPO3/core-patches/actions/workflows/continuous-integration.yml)

This package let you easily manage TYPO3 Core patches with Composer with the help
of `cweagans/composer-patches` which is a dependency of this package and requires
to be installed as non-dev dependency.

**Table of contents**:

- [Installation](#installation)
- [Usage](#usage)
  - [Adding a change](#adding-a-change)
  - [Removing a change](#removing-a-change)
  - [Supported change ID formats](#supported-change-id-formats)
- [Feedback / Bug reports / Contribution](#feedback--bug-reports--contribution)
- [License](#license)

## Installation

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

## Feedback / Bug reports / Contribution

Bug reports, feature requests and pull requests are welcome in the [GitHub
repository](https://github.com/GsTYPO3/core-patches).

Currently there is an [on going issue](https://github.com/GsTYPO3/core-patches/issues/1)
to provide feedback. For bigger requests please open a dedicated issue instead.

## License

This package is licensed under the [MIT License](LICENSE).
