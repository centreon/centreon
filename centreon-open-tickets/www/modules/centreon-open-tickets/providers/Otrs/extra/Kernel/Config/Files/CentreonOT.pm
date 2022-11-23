# OTRS config file
# VERSION:1.1

package Kernel::Config::Files::CentreonOT;

use strict;
use warnings;
no warnings 'redefine';

use utf8;

sub Load {
    my ($File, $Self) = @_;

    $Self->{'GenericInterface::Operation::Module'}->{'Priority::PriorityGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Priority',
        'Name' => 'PriorityGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'Queue::QueueGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Queue',
        'Name' => 'QueueGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'State::StateGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'State',
        'Name' => 'StateGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'Type::TypeGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'Type',
        'Name' => 'TypeGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'CustomerUser::CustomerUserGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'CustomerUser',
        'Name' => 'CustomerUserGet'
    };
    $Self->{'GenericInterface::Operation::Module'}->{'DynamicField::DynamicFieldGet'} =  {
        'ConfigDialog' => 'AdminGenericInterfaceOperationDefault',
        'Controller' => 'DynamicField',
        'Name' => 'DynamicFieldGet'
    };
}

1;
