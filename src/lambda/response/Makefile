#
#   Package up any node_modules that need to be compiled specifically for Lambda
#   into a .zip file which the Craft plugin will then add to the deployed bundle
#

all: clean build

build:
	SHARP_IGNORE_GLOBAL_LIBVIPS=1 npm install --prefix ./ --arch=x64 --platform=linux --libc=glibc sharp
	zip -r node_modules.zip node_modules
	rm -rf node_modules && rm -f package*

clean:
	rm -f node_modules.zip
