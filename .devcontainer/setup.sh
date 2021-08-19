#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" >/dev/null 2>&1 && pwd )"

cd $DIR/

cd ..
git clone https://github.com/nextcloud/server --depth=1
cd server
git submodule update --init

# Codespace config
cp $DIR/.devcontainer/codespace.config.php config/codespace.config.php

mv $DIR apps/

cd ..

mv server $(dirname "$DIR")
