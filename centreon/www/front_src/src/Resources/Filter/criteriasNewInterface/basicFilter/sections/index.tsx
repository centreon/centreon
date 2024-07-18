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
  data,
  changeCriteria,
  searchData
}: Omit<MemoizedChild, 'filterName'>): JSX.Element => {
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
                changeCriteria={changeCriteria}
                data={data}
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
                changeCriteria={changeCriteria}
                data={data}
                filterName={
                  equals(sectionType, SectionType.host)
                    ? BasicCriteria.parentNames
                    : BasicCriteria.names
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
          <Divider className={classes.divider} />
        </>
      ))}
    </div>
  );
};

export default SectionWrapper;
