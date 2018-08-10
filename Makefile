.DEFAULT_GOAL := build

.PHONY: build
build: lint test

.PHONY: cs-fix
cs-fix:
	@tools/php-cs-fixer fix

.PHONY: lint
lint:
	@composer validate --strict
	@tools/php-cs-fixer fix --diff --dry-run -v

.PHONY: test
test:
	@tools/phpunit
