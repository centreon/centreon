const BasicFilter = ({ sections, poller, state }): JSX.Element => {
  return (
    <div style={{ minWidth: 200 }}>
      {sections}
      {poller}
      {state}
    </div>
  );
};

export default BasicFilter;
