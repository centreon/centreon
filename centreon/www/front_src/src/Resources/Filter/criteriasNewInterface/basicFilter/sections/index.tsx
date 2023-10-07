import { Divider } from '@mui/material';

import { MemoizedChild, SectionType } from '../../model';

import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import MemoizedStatus from './MemoizedStatus';
import Section from './Section';

const SectionWrapper = ({
  basicData,
  changeCriteria
}: MemoizedChild): JSX.Element => {
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
          <Divider sx={{ marginBottom: 5 }} />
        </>
      ))}
    </div>
  );
};

export default SectionWrapper;
