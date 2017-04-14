build:
	docker build -t dipo .

start:
	docker run --rm -d -p 8080:2015 --name dipo \
		-v ${PWD}/content:/srv/content \
		-v ${PWD}/custom:/srv/custom \
		-v ${PWD}/web/portfolio-content:/srv/web/portfolio-content \
		dipo

stop:
	docker rm -f dipo

restart: stop start

shell:
	docker exec -it dipo bash

.PHONY: build start stop restart shell
