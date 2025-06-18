import { and, isEmpty, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Button, Divider, Typography } from '@mui/material';

import { ProviderConfiguration } from './models';
import { labelLoginWith, labelOr } from './translatedLabels';

interface Props {
  providersConfiguration: Array<ProviderConfiguration> | null;
}

const useStyles = makeStyles()((theme) => ({
  otherProvidersContainer: {
    display: 'flex',
    flexDirection: 'column',
    marginTop: theme.spacing(1),
    rowGap: theme.spacing(1),
    width: '100%'
  }
}));

const ExternalProviders = ({
  providersConfiguration
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const hasProvidersConfiguration = and(
    !isNil(providersConfiguration),
    !isEmpty(providersConfiguration)
  );

  return (
    <>
      {hasProvidersConfiguration && (
        <div className={classes.otherProvidersContainer}>
          <Divider>
            <Typography>{t(labelOr)}</Typography>
          </Divider>
          {providersConfiguration?.map(({ name, authenticationUri }) => {
            const dataTestId = `${labelLoginWith} ${name}`;
            const data = `${t(labelLoginWith)} ${name}`;

            return (
              <Button
                aria-label={data}
                color="primary"
                data-testid={dataTestId}
                href={authenticationUri}
                key={name}
                variant="contained"
              >
                {data}
              </Button>
            );
          })}
        </div>
      )}
    </>
  );
};

export default ExternalProviders;
