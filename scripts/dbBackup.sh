#!/bin/bash
cd ..

mysqldump ampletracks | gzip > `date '+ampletracks_%Y%m%d.sql.gz'`
mysqldump ampletracks_dev | gzip > `date '+ampletracks_dev_%Y%m%d.sql.gz'`
