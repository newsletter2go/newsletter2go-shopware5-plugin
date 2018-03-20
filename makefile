version = $(subst .,_,${shell grep -oP "(?<=VERSION = ').*?(?=')" Core/Newsletter2Go/Bootstrap.php})
outfile = Shopware_nl2go_$(version).zip

$(version): $(outfile)

$(outfile):
	zip -r  build.zip ./Core/*
	mv build.zip $(outfile)

clean:
	rm -rf tmp
