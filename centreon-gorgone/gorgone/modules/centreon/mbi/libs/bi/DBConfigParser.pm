# 
# Copyright 2019 Centreon (http://www.centreon.com/)
#
# Centreon is a full-fledged industry-strength solution that meets
# the needs in IT infrastructure and application monitoring for
# service performance.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

use strict;
use warnings;
use POSIX;
use XML::LibXML;
use Data::Dumper;

package gorgone::modules::centreon::mbi::libs::bi::DBConfigParser;

# Constructor
# parameters:
# $logger: instance of class CentreonLogger
# $centreon: Instance of centreonDB class for connection to Centreon database
# $centstorage: (optionnal) Instance of centreonDB class for connection to Centstorage database

sub new {
	my $class = shift;
	my $self  = {};
	$self->{"logger"}	= shift;
	bless $self, $class;
	return $self;
}

sub parseFile {
    my $self = shift;
    my $logger =  $self->{"logger"};
    my $file = shift;

    my %connProfiles = ();
    if (! -r $file) {
	$logger->writeLog("ERROR", "Cannot read file ".$file);
    }
    my $parser = XML::LibXML->new();
    my $root  = $parser->parse_file($file);
    foreach my $profile ($root->findnodes('/DataTools.ServerProfiles/profile')) {
		my $base = $profile->findnodes('@name');
		   
		foreach my $property ($profile->findnodes('./baseproperties/property')) {
			my $name = $property->findnodes('@name')->to_literal;
			my $value = $property->findnodes('@value')->to_literal;
			if ($name eq 'odaURL') {
				if ($value =~ /jdbc\:[a-z]+\:\/\/([^:]*)(\:\d+)?\/(.*)/) {
					$connProfiles{$base."_host"} = $1;
					if(defined($2) && $2 ne ''){  
						$connProfiles{$base."_port"} = $2; 
						$connProfiles{$base."_port"} =~ s/\://;
					}else{
						$connProfiles{$base."_port"} = '3306';
					}
					$connProfiles{$base."_db"} = $3;
				   $connProfiles{$base."_db"} =~ s/\?autoReconnect\=true//;
				}
			}
			if ($name eq 'odaUser') {
			$connProfiles{$base."_user"} = sprintf('%s',$value);
			}
			if ($name eq 'odaPassword') {
			$connProfiles{$base."_pass"} = sprintf('%s', $value);
			}
		}
    }
	
    return (\%connProfiles);
}
   
1;
