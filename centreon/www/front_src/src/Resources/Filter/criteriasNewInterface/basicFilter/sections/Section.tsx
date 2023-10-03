const Section = ({ status, inputGroup, selectInput }) => {
  return (
    <>
      {selectInput}
      {status}
      {inputGroup}
    </>
  );
};

export default Section;
