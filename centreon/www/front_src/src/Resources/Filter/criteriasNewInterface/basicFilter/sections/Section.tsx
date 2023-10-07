interface Section {
  inputGroup: JSX.Element;
  selectInput: JSX.Element;
  status: JSX.Element;
}

const Section = ({ status, inputGroup, selectInput }: Section): JSX.Element => {
  return (
    <>
      {selectInput}
      {status}
      {inputGroup}
    </>
  );
};

export default Section;
