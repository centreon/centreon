import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Container, Paper, Typography } from '@mui/material';

import { CentreonLogo } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import SectionTitle from './SectionTitle';
import Community from './Sections/Community';
import Contributors from './Sections/Contibutors';
import Copyright from './Sections/Copyright';
import Developers from './Sections/Developers';
import ProjectLeaders from './Sections/ProjectLeaders';
import {
  labelCentreon,
  labelContributors,
  labelDevelopers,
  labelManyThanksToAllContributorsToTheSecurity,
  labelProjectLeaders,
  labelSecurityAcknowledgement
} from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  aboutContainer: {
    alignItems: 'flex-start',
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: '0.5fr 1fr',
    maxHeight: '85vh',
    overflowY: 'auto',
    padding: theme.spacing(2),
    rowGap: theme.spacing(2)
  },
  developerSection: {
    alignItems: 'center',
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: '0.8fr 1fr'
  }
}));

const About = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const platformVersion = useAtomValue(platformVersionsAtom);

  return (
    <Container maxWidth="md">
      <Paper className={classes.aboutContainer}>
        <SectionTitle
          title={`${labelCentreon} ${platformVersion?.web.version}`}
        />
        <Community />
        <SectionTitle title={labelProjectLeaders} />
        <ProjectLeaders />
        <SectionTitle title={labelDevelopers} />
        <div className={classes.developerSection}>
          <Developers />
          <CentreonLogo />
        </div>
        <SectionTitle title={labelContributors} />
        <Contributors />
        <SectionTitle title={labelSecurityAcknowledgement} />
        <Typography>
          {t(labelManyThanksToAllContributorsToTheSecurity)}
        </Typography>
        <div />
        <Copyright />
      </Paper>
    </Container>
  );
};

export default About;
