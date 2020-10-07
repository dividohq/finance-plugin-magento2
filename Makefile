ci-test: 

ci-clean:

ci-analyze:

ci-fmt:
	docker run --rm -v $$(pwd):/project -e FOLDERS=Api,Block,Controller,Helper,Logger,Model,Observer,Setup divido/devtools:php-fmt

ci-check-coverage:

ci-build: #noop

ci-push: #noop

quick-local-test: ci-clean ci-test ci-check-coverage