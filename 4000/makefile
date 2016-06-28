version = 0_0_00
outfile = Shopware_nl2go_$(version).zip

$(version): $(outfile)

$(outfile):
	zip -r  build.zip ./Core/*
	mv build.zip $(outfile)


clean:
	rm -rf tmp
