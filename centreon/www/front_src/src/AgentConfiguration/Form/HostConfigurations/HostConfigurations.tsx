import { Button, IconButton } from '@centreon/ui/components';
import { AddCircleOutline } from '@mui/icons-material';
import DeleteOutline from '@mui/icons-material/DeleteOutline';
import { Box, Divider } from '@mui/material';
import { Fragment } from 'react';
import { useTranslation } from 'react-i18next';
import { labelAddAHost } from '../../translatedLabels';
import HostConfiguration from './HostConfiguration';
import { useHostConfigurationsStyle } from './HostConfigurationsStyle';
import { useHostConfigurations } from './useHostConfigurations';

const HostConfigurations = () => {
  const { classes } = useHostConfigurationsStyle();
  const { t } = useTranslation();

  const { hosts, addHostConfiguration, deleteHostConfiguration } =
    useHostConfigurations();

  return (
    <Box className={classes.hostConfigurations}>
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
                icon={<DeleteOutline fontSize="small" color="disabled" />}
                className={classes.deleteButton}
                onClick={deleteHostConfiguration(index)}
              />
            </Box>
          </Box>
          <Divider />
        </Fragment>
      ))}
      <Button
        iconVariant="start"
        variant="ghost"
        icon={<AddCircleOutline />}
        className={classes.addButton}
        onClick={addHostConfiguration}
      >
        {t(labelAddAHost)}
      </Button>
    </Box>
  );
};

export default HostConfigurations;
