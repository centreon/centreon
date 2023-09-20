import { Divider } from '@mui/material';

import { CheckBoxWrapper as StatusSection } from '../CheckBox';
import { BasicCriteria, SectionType } from '../../model';
import SelectInput from '../SelectInput';
import InputGroup from '../InputGroupe';

import Section from './Section';

const SectionWrapper = ({ basicData, changeCriteria }): JSX.Element => {
  const sectionsType = Object.values(SectionType);

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
                sectionType={sectionType}
              />
            )}
            renderStatus={({ sectionData }) => (
              <StatusSection
                changeCriteria={changeCriteria}
                data={sectionData}
                filterName={BasicCriteria.statues}
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
