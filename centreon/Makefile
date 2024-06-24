TARGET = input.json
SOURCE = composer.json
MY_FLAG = 3

# Définir la commande pour obtenir les fichiers modifiés ou ajoutés dans le git status actuel
GET_CHANGED_FILES = git diff --name-status HEAD~1 | grep -oe "^[A|M].*\.php" | sed -r 's/[A |M ].centreon\/(.*)/\1/' | tr '\n' ' '
FILES=$(strip $(shell $(GET_CHANGED_FILES)))
# Définir la commande PHPStan
PHPSTAN := vendor/bin/phpstan
level = 4
PHPSTAN_LEVEL = $(level)
ECHO = @echo

TEST =
FILTERPATH = "./src/Core/"
TESTPATH = "./tests/php/Core/"

INFO = ./test-info
PEST =XDEBUG_MODE=coverage vendor/bin/pest --colors=never --coverage-filter=$(FILTERPATH)$(TEST) --coverage-text=coverage_summary.txt $(TESTPATH)$(TEST)

# Règle par défaut
.DEFAULT_GOAL := help

# Règle help
help:
	$(ECHO) "Voici les commandes disponibles dans ce Makefile :"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[38;5;5m%-30s\033[0m %s\n", $$1, $$2}'


clean: ## Clean vendor's directory if exist
ifeq (shell $(test -s vendor),)
	echo "Vendor's file unknow"
else
	rm -rf vendor
endif

install: ## Do a composer install if vendor is missing
	test -s vendor &&  echo "vendor's file is good :)" || composer install

update: check ## Do a composer update if composer.json change

check: $(TARGET)
	@echo "Le fichier $(SOURCE) est opérationnel"

$(TARGET): $(SOURCE)
	composer update
	@touch $(TARGET)

phpstan: ## Do a analyse on all modified files in the current commit (Do a LEVEL= 1-8 to select the level of analyse you want, default level = 4 )
ifeq ($(FILES),)
	@echo "nothing to analyse"
else
	$(PHPSTAN) analyse --level=$(PHPSTAN_LEVEL) $(FILES) || true
endif

phpstan_neon: ## Do a analyse on all modified files in the current commit with phpstan.neon
ifeq (shell $(test -s phpstan.neon),)
	echo "No file to analyse"
else
	vendor/bin/phpstan analyse --level=4 --configuration=phpstan.neon $(FILES) || true
endif

phpstan_core_neon: ## Do a analyse on all modified files in the current commit with phpstan.core.neon level 8
ifeq (shell $(test -s phpstan.core.neon),)
	echo "No file to analyse"
else
	vendor/bin/phpstan analyse --level=8 --configuration=phpstan.core.neon $(FILES) || true
endif

phpstan_test_neon: ## Do a analyse on all modified files in the current commit with phpstan.test.neon level 2
ifeq (shell $(test -s phpstan.test.neon),)
	echo "No file to analyse"
else
	vendor/bin/phpstan analyse --level=2 --configuration=phpstan.test.neon $(FILES) || true
endif

phpstan_all_neon:  ## Do a analyse on all modified files in the current commit with all the 3 rules above
	@$(MAKE) phpstan_neon
	@$(MAKE) phpstan_core_neon
	@$(MAKE) phpstan_test_neon

fixer_strict: ## Do a strict fix on all modified files in the current commit
ifeq ($(FILES),)
	@echo "nothing to fix"
else
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --verbose --dry-run --diff $(FILES) || true
endif

fixer_unstrict: ## Do a unstrict fix on all modified files in the current commit
ifeq ($(FILES),)
	@echo "nothing to fix"
else
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.unstrict.php --dry-run --diff $(FILES) || true
endif


coverage: ## Do a coverage of all src/Core or can be adjust with FILTER=<folder> for having the coverage of this folder
	@XDEBUG_MODE=coverage ./vendor/bin/pest >/dev/null --coverage-filter=$(FILTER) --coverage-text=coverage_summary.txt || true
	@echo ''
	@sed -n '9p' coverage_summary.txt
	@echo ''
	@echo "  In $(FILTER)'s directory"
	@echo ''



test-pest: ## Do a test on all file and show you the coverage if there is no failed test
	@echo "Running Pest tests..."
	@OUTPUT=`$(PEST) >test-info.txt 2>&1`; \
	RETVAL=$$?; \
if [ $$RETVAL -eq 0 ]; then \
	echo "Pest tests passed"; \
	echo ''; \
	sed -n '9p' ./coverage_summary.txt ; \
	echo ''; \
else \
  	echo ''; \
	grep "^  Tests:" test-info.txt ; \
	echo ''; \
	echo "Pest tests failed"; \
fi
