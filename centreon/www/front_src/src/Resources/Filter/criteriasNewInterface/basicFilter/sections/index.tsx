import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { Divider } from '@mui/material';

import { BasicCriteria, MemoizedChild, SectionType } from '../../model';
import { selectedVisualizationAtom } from '../../../../Actions/actionsAtoms';
import { Visualization } from '../../../../models';

import { useStyles } from './sections.style';
import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import MemoizedStatus from './MemoizedStatus';
import Section from './Section';

const SectionWrapper = ({
  basicData,
  changeCriteria,
  searchData
}: MemoizedChild): JSX.Element => {
  const { classes } = useStyles();
  const selectedVisualization = useAtomValue(selectedVisualizationAtom);
  const sectionsType = Object.values(SectionType);

  const isViewByHost = equals(selectedVisualization, Visualization.Host);

  const deactivateInput = (sectionType: SectionType): boolean => {
    return isViewByHost && equals(sectionType, SectionType.host);
  };

  return (
    <div>
      {sectionsType?.map((sectionType) => (
        <>
          <Section
            inputGroup={
              <MemoizedInputGroup
                basicData={basicData}
                changeCriteria={changeCriteria}
                filterName={
                  equals(sectionType, SectionType.host)
                    ? BasicCriteria.hostGroups
                    : BasicCriteria.serviceGroups
                }
                sectionType={sectionType}
              />
            }
            selectInput={
              <MemoizedSelectInput
                basicData={basicData}
                changeCriteria={changeCriteria}
                filterName={
                  equals(sectionType, SectionType.host)
                    ? BasicCriteria.parentNames
                    : BasicCriteria.names
                }
                isDeactivated={deactivateInput(sectionType)}
                searchData={searchData}
                sectionType={sectionType}
              />
            }
            status={
              <MemoizedStatus
                basicData={basicData}
                changeCriteria={changeCriteria}
                filterName={BasicCriteria.statues}
                isDeactivated={deactivateInput(sectionType)}
                sectionType={sectionType}
              />
            }
          />
          <Divider className={classes.divider} />
        </>
      ))}
    </div>
  );
};

export default SectionWrapper;
