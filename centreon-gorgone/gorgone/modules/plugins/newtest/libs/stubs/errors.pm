
package gorgone::modules::plugins::newtest::libs::stubs::errors;

use strict;
use warnings;

our $SOAP_ERRORS;

sub soapGetBad {
    my $soap = shift;
    my $res = shift;
    
    if(ref($res)) {
        chomp( my $err = $res->faultstring );
        $SOAP_ERRORS = "SOAP FAULT: $err";
    } else {
        chomp( my $err = $soap->transport->status );
        $SOAP_ERRORS = "TRANSPORT ERROR: $err";
    }
    return new SOAP::SOM;
}

sub get_error {
   my $error = $SOAP_ERRORS;
   
   $SOAP_ERRORS = undef;
   return $error;
}

1;

