const BasicFilter = ({ sections, poller, state }): JSX.Element => {
  return (
    <div>
      {sections}
      {poller}
      {state}
    </div>
  );
};

export default BasicFilter;
