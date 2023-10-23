import { equals } from 'ramda';

import { Divider } from '@mui/material';

import { BasicCriteria, MemoizedChild, SectionType } from '../../model';

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
  const sectionsType = Object.values(SectionType);

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
                searchData={searchData}
                sectionType={sectionType}
              />
            }
            status={
              <MemoizedStatus
                basicData={basicData}
                changeCriteria={changeCriteria}
                filterName={BasicCriteria.statues}
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
