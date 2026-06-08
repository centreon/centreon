import { Typography } from '@mui/material';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { selectedVisualizationAtom } from '../../../../Actions/actionsAtoms';
import { Visualization } from '../../../../models';
import { labelHost, labelService } from '../../../../translatedLabels';
import { BasicCriteria, MemoizedChild, SectionType } from '../../model';
import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import MemoizedStatus from './MemoizedStatus';
import Section from './Section';
import { useStyles } from './sections.style';

const SectionWrapper = ({
  data,
  changeCriteria,
  searchData
}: Omit<MemoizedChild, 'filterName'>): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const selectedVisualization = useAtomValue(selectedVisualizationAtom);
  const sectionsType = Object.values(SectionType);

  const isViewByHost = equals(selectedVisualization, Visualization.Host);

  const deactivateInput = (sectionType: SectionType): boolean => {
    return isViewByHost && equals(sectionType, SectionType.host);
  };

  return (
    <div className={classes.sectionsGrid}>
      {sectionsType?.map((sectionType) => {
        const isHost = equals(sectionType, SectionType.host);

        return (
          <div className={classes.sectionColumn} key={sectionType}>
            <Typography className={classes.sectionTitle}>
              {t(isHost ? labelHost : labelService)}
            </Typography>
            <Section
              inputGroup={
                <MemoizedInputGroup
                  changeCriteria={changeCriteria}
                  data={data}
                  filterName={
                    isHost
                      ? BasicCriteria.hostGroups
                      : BasicCriteria.serviceGroups
                  }
                  sectionType={sectionType}
                />
              }
              selectInput={
                <MemoizedSelectInput
                  changeCriteria={changeCriteria}
                  data={data}
                  filterName={
                    isHost ? BasicCriteria.parentNames : BasicCriteria.names
                  }
                  searchData={searchData}
                  sectionType={sectionType}
                />
              }
              status={
                <MemoizedStatus
                  changeCriteria={changeCriteria}
                  data={data}
                  filterName={BasicCriteria.statues}
                  isDeactivated={deactivateInput(sectionType)}
                  sectionType={sectionType}
                />
              }
            />
          </div>
        );
      })}
    </div>
  );
};

export default SectionWrapper;
