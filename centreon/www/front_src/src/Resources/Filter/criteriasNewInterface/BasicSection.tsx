import CheckBoxWrapper from './CheckBox';
import InputGroup from './InputGroupe';
import SelectInput from './SelectInput';

const BasicSection = ({ resourceType, status }) => {
  return (
    <div>
      <SelectInput type={resourceType} />
      <CheckBoxWrapper data={status} />
      <InputGroup type={resourceType} />
    </div>
  );
};

export default BasicSection;
