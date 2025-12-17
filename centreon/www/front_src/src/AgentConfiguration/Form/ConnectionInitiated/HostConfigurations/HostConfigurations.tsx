import DeleteOutline from '@mui/icons-material/DeleteOutline';
import { Box, Divider } from '@mui/material';

import { IconButton } from '@centreon/ui/components';

import { equals } from 'ramda';
import { Fragment } from 'react';

import { labelMonitoredHosts } from '../../../translatedLabels';
import Title from '../../ConnectionInitiated/Title';
import AddButton from './AddButton';
import HostConfiguration from './HostConfiguration';
import { useHostConfigurationsStyle } from './HostConfigurationsStyle';
import { useHostConfigurations } from './useHostConfigurations';

const HostConfigurations = () => {
  const { classes } = useHostConfigurationsStyle();

  const { hosts, addHostConfiguration, deleteHostConfiguration } =
    useHostConfigurations();

  return (
    <Box className={classes.hostConfigurations}>
      <Title label={labelMonitoredHosts} />
      {hosts?.map((host, index) => (
        <Fragment key={index.toString()}>
          <Box
            sx={{
              display: 'flex',
              flexDirection: 'row',
              position: 'relative',
              width: 'calc(100% - 15px)'
            }}
          >
            <HostConfiguration index={index} host={host} />
            <Box className={classes.deleteContainer}>
              <IconButton
                color="default"
                size="small"
                icon={
                  <DeleteOutline
                    fontSize="small"
                    className={classes.deleteIcon}
                  />
                }
                className={classes.deleteButton}
                onClick={deleteHostConfiguration(index)}
                data-testid={`delete-host-configuration-${index}`}
                disabled={equals(1, hosts.length)}
              />
            </Box>
          </Box>
          {!equals(index, hosts.length - 1) && (
            <Divider className={classes.divider} variant="middle" />
          )}
        </Fragment>
      ))}
      <AddButton addButtonDisabled={false} onAddItem={addHostConfiguration} />
    </Box>
  );
};

export default HostConfigurations;
