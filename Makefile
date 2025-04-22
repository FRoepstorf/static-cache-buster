.PHONY: ci
ci: rector composer-check csfix test

.PHONY: csfix
csfix: ## runs pint cs fixer
	./vendor/bin/pint

.PHONY: rector
rector: ## Runs rector
	./vendor/bin/rector

.PHONY: test
test: ## Runs phpunit
	./vendor/bin/phpunit

.PHONY: composer-check
composer-check: ## Checks composer.json and composer.lock
	./vendor/bin/composer-dependency-analyser --ignore-shadow-deps