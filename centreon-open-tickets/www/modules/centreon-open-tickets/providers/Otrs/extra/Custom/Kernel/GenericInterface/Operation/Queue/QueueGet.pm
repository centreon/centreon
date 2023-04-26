
package Kernel::GenericInterface::Operation::Queue::QueueGet;

use strict;
use warnings;

use Kernel::System::VariableCheck qw(IsStringWithData IsHashRefWithData);
use Kernel::System::ObjectManager;

use base qw(
    Kernel::GenericInterface::Operation::Common
);

our $ObjectManagerDisabled = 1;

sub new {
    my ( $Type, %Param ) = @_;

    my $Self = {};
    bless( $Self, $Type );

    # check needed objects
    for my $Needed (qw(DebuggerObject)) {
        if ( !$Param{$Needed} ) {
            return {
                Success      => 0,
                ErrorMessage => "Got no $Needed!"
            };
        }

        $Self->{$Needed} = $Param{$Needed};
    }

    return $Self;
}

sub Run {
    my ($Self, %Param) = @_;

    # check needed stuff
    if (!$Param{Data}->{UserLogin} && !$Param{Data}->{CustomerUserLogin} && !$Param{Data}->{SessionID}) {
        return $Self->ReturnError(
            ErrorCode    => 'QueueGet.MissingParameter',
            ErrorMessage => "QueueGet: UserLogin, CustomerUserLogin or SessionID is required!",
        );
    }

    if ($Param{Data}->{UserLogin} || $Param{Data}->{CustomerUserLogin}) {
        if (!$Param{Data}->{Password}) {
            return $Self->ReturnError(
                ErrorCode    => 'QueueGet.MissingParameter',
                ErrorMessage => "QueueGet: Password or SessionID is required!",
            );
        }
    }


    # check data - only accept undef or hash ref
    if (defined $Param{Data} && ref $Param{Data} ne 'HASH') {
        return $Self->{DebuggerObject}->Error(
            Summary => 'Got Data but it is not a hash ref in Operation Test backend)!'
        );
    }

    # authenticate user
    my ($UserID, $UserType) = $Self->Auth(%Param);
    if (!$UserID) {
        return $Self->ReturnError(
            ErrorCode    => 'QueueGet.AuthFail',
            ErrorMessage => "QueueGet: User could not be authenticated!",
        );
    }

    $Kernel::OM = Kernel::System::ObjectManager->new();
    my $QueueObject = $Kernel::OM->Get('Kernel::System::Queue');
    my %Queues = $QueueObject->GetAllQueues(UserID => $UserID);
    if (!%Queues) {
        return {
            Success      => 0,
            ErrorMessage => "Cannot get queue list",
            Data         => {
            },
        };
    }

    my $data = { response => [] };
    foreach my $id (sort { lc($Queues{$a}) cmp lc($Queues{$b}) } keys %Queues) {
        push @{$data->{response}}, { id => $id, name => $Queues{$id}};
    }
    return {
        Success => 1,
        Data    => $data,
    };
}

1;
