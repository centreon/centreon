#!/bin/bash

set -ex

PROJECT="centreon-plugin-packs"
VERSION=$1
COMMIT=$2
now=`date +%s`

export RELEASE="$now.$COMMIT"

if [ ! -d /root/rpmbuild/SOURCES ] ; then
    mkdir -p /root/rpmbuild/{BUILD,BUILDROOT,RPMS,SOURCES,SPECS,SRPMS}
fi

cd /src
if [ -d $PROJECT/packs ]; then
    rm -rf $PROJECT/packs
fi
mkdir -p $PROJECT/packs
/usr/bin/python3 << EOF
import json
from os import listdir

output = """Name:           centreon-pack
Version:        1.0.0
Release:        1%{?dist}
Summary:        Centreon Pack

Group:          Applications/System
License:        Proprietary
URL:            https://www.centreon.com

Source0:        packs.tar.gz

BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch:      noarch

%prep
%setup -q -n packs

%description
Centreon Pack root package. Not meant to be installed.

%clean
rm -rf \$RPM_BUILD_ROOT

%install
rm -rf \$RPM_BUILD_ROOT
%{__install} -d \$RPM_BUILD_ROOT%{_datadir}/centreon-packs
%{__cp} *.json \$RPM_BUILD_ROOT%{_datadir}/centreon-packs/

"""

for pack in listdir('$PROJECT/src'):
    with open('$PROJECT/src/%s/pack.json' % pack) as jfile:
        data = json.loads(jfile.read())

        output += '%%package %s\n' % pack
        output += 'Summary:  Centreon pack\n'
        output += 'Version:  %s\n' % data['information']['version']
        output += 'Requires: centreon-pp-manager >= 2.0\n'
        output += 'Obsoletes: ces-pack-@OLDPACK@\n'
        output += 'Provides: ces-pack-@OLDPACK@\n\n'
        output += '%%description %s\n' % pack
        output += '%s\n\n' % pack
        output += '%%files %s\n' % pack
        output += '%%defattr(-,root,root,-)\n'
        output += '%%{_datadir}/centreon-packs/pluginpack_%s-%s.json' % (
            pack, data['information']['version']
        )

        # Make source json package
        with open(
            '$PROJECT/packs/pluginpack_%s-%s.json' % (
                pack,
                data['information']['version']
            ), 'w+'
        ) as wjson:
            json.dump(data, wjson, indent=4)

# Make spec rpm package file
with open(
    '/tmp/centreon-plugin-packs.spec', 'w+'
) as installFile:
    installFile.write(output)

EOF

rm -rf /tmp/$PROJECT-$VERSION
mkdir /tmp/$PROJECT-$VERSION
cp -rp plugin-packs/packs/* /tmp/$PROJECT-$VERSION/
(cd /tmp && tar czf /root/rpmbuild/SOURCES/$PROJECT-$VERSION.tar.gz $PROJECT-$VERSION)
rm -rf /root/rpmbuild/RPMS/*

cd /tmp/$PROJECT
rpmbuild -ba /tmp/centreon-plugin-packs.spec
cp -r /root/rpmbuild/RPMS/noarch/*.rpm .
chmod 777 *.rpm
cd ..

find /src -type f -iname '*.rpm' | xargs cp -vt /src/

exit 0
