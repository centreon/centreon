
Here the procedure to install the webservice for centreon-open-tickets:
* Copy the directory 'Custom' and 'Kernel' in root otrs directory (eg. /opt/otrs/)
* Add following lines in 'Kernel/Config.pm':

```
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
```

* Create a 'centreon' HTTP::REST webservice in otrs-ui
* add following operations in 'centreon' webservice provider: 
  * CustomerUser::CustomerUserGet
  * Priority::PriorityGet 
  * Queue::QueueGet
  * Session::SessionCreate
  * State::StateGet
  * Ticket::TicketCreate
  * Type::TypeGet
* configure the webservice provider mapping (you need to do it for all operations!!). For example: 
  * 'CustomerUser::CustomerUserGet' set '/CustomerUserGet/'