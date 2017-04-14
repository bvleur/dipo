build:
	docker build -t dipo .

start:
	docker run --rm -d -p 8080:2015 --name dipo \
		-v ${PWD}/content:/srv/content \
		-v ${PWD}/custom:/srv/custom \
		-v ${PWD}/web/portfolio-content:/srv/web/portfolio-content \
		dipo

start-dev:
	docker run --rm -d -p 8080:2015 --name dipo \
		-v ${PWD}:/srv \
		dipo

stop:
	docker rm -f dipo

restart: stop start
restart-dev: stop start-dev

shell:
	docker exec -it dipo bash

.PHONY: build start start-dev stop restart shell
