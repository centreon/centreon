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
            <HostConfiguration host={host} index={index} />
            <Box className={classes.deleteContainer}>
              <IconButton
                className={classes.deleteButton}
                color="default"
                data-testid={`delete-host-configuration-${index}`}
                disabled={equals(1, hosts.length)}
                icon={
                  <DeleteOutline
                    className={classes.deleteIcon}
                    fontSize="small"
                  />
                }
                onClick={deleteHostConfiguration(index)}
                size="small"
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
