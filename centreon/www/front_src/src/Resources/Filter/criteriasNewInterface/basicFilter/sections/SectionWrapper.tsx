import { Divider } from '@mui/material';

import { CheckBoxSection as StatusSection } from '../CheckBox';
import { BasicCriteria, SectionType } from '../../model';
import SelectInput from '../SelectInput';
import InputGroup from '../InputGroupe';

import Section from './Section';

const SectionWrapper = ({ basicData, changeCriteria }): JSX.Element => {
  const sectionsType = Object.values(SectionType);
  console.log({ basicData });

  return (
    <div>
      {sectionsType?.map((sectionType) => (
        <>
          <Section
            data={basicData}
            renderInputGroup={({ sectionData }) => (
              <InputGroup
                changeCriteria={changeCriteria}
                data={sectionData}
                filterName={
                  sectionType === SectionType.host
                    ? BasicCriteria.hostGroups
                    : BasicCriteria.serviceGroups
                }
              />
            )}
            renderSelectInput={({ sectionData }) => (
              <SelectInput
                changeCriteria={changeCriteria}
                data={sectionData}
                filterName={BasicCriteria.resourceTypes}
                resourceType={sectionType}
              />
            )}
            renderStatus={({ sectionData }) => (
              <StatusSection
                changeCriteria={changeCriteria}
                data={sectionData}
                filterName={BasicCriteria.statues}
                resourceType={sectionType}
              />
            )}
            sectionType={sectionType}
          />
          <Divider sx={{ marginBottom: 5 }} />
        </>
      ))}
    </div>
  );
};

export default SectionWrapper;
