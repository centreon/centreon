import { Divider } from '@mui/material';

import { MemoizedChild, SectionType } from '../../model';

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
                sectionType={sectionType}
              />
            }
            selectInput={
              <MemoizedSelectInput
                basicData={basicData}
                changeCriteria={changeCriteria}
                searchData={searchData}
                sectionType={sectionType}
              />
            }
            status={
              <MemoizedStatus
                basicData={basicData}
                changeCriteria={changeCriteria}
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
