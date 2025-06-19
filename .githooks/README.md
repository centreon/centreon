## `Git` hooks

One can enable shared `git` hooks by using the following command:

```bash
git config --global core.hooksPath .githooks
```

It tells `git` to run any name-compliant executables found in the `.githooks` repository.
It enables this option globally to avoid to need to run it on each repositories.