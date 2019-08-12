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

package centreon::gorgone::module;

use centreon::gorgone::common;

use constant ACTION_BEGIN => 0;
use constant ACTION_FINISH_KO => 1;
use constant ACTION_FINISH_OK => 2;

sub generate_token {
   my ($self, %options) = @_;
   
   return centreon::gorgone::common::generate_token();
}

sub send_log {
    my ($self, %options) = @_;

    return if (!defined($options{token}));

    if (!defined($self->{socket_log})) {
        $self->{socket_log} = centreon::gorgone::common::connect_com(
            zmq_type => 'ZMQ_DEALER', name => $self->{module_id} . '-'. $self->{container_id},
            logger => $self->{logger}, linger => 5000,
            type => $self->{config_core}->{internal_com_type},
            path => $self->{config_core}->{internal_com_path}
        );
    }

    centreon::gorgone::common::zmq_send_message(
        socket => $self->{socket_log},
        action => 'PUTLOG', 
        data => { code => $options{code}, etime => time(), token => $options{token}, data => $options{data} },
        json_encode => 1
    );
}



1;
