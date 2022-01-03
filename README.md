# TYPO3 Core Patches

This package let you easily manage TYPO3 Core patches with Composer with the help
of `cweagans/composer-patches` which is a dependency of this package and requires
to be installed as non-dev dependency.

## Installation

Require this package as normal dependency:

```bash
composer require gilbertsoft/typo3-core-patches
```

If the package is installed as dev requirement, the patches won't get applied
using the install option `--no-dev`.

## Adding a change

Lookup the change ID at <https://forge.typo3.org> or <https://review.typo3.org>
and provide it as argument or multiple arguments for multiple changes at once:

```bash
composer typo3:core-patch:add 12345
composer typo3:core-patch:add 12345 23456 34567
```

This plugin will then properly create patch files for the change and save it to
the patch directory which defaults to `patches`. The patch directory can be
changed by the option `--patch-dir` or with the shortcut `-p`:

```bash
composer typo3:core-patch:add --patch-dir:path/to/folder 12345
composer typo3:core-patch:add -ppath/to/folder 12345
```

By default changes in tests are exluded. If you also like to include these
changes, provide the option `--tests` or the shortcut `-t`. This will result in
installing the sources instead of the dist packages for the affected packages.

```bash
composer typo3:core-patch:add --tests 12345
composer typo3:core-patch:add -t 12345
```

## Removing a change

Provide the change ID to remove as argument or multiple arguments for multiple
changes to remove at once:

```bash
composer typo3:core-patch:remove 12345
composer typo3:core-patch:remove 12345 23456 34567
```

## Feedback / Bug reports / Contribution

Bug reports, feature requests and pull requests are welcome in the GitHub
repository: <https://github.com/GsTYPO3/core-patches>

## License

This package is licensed under the [MIT License](LICENSE).
