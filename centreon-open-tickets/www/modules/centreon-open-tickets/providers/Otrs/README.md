
Here the procedure to install the webservice for centreon-open-tickets:
* Copy the directory 'Custom' and 'Kernel' in root otrs directory (eg. /opt/otrs/)
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
