interface BasicFilter {
  poller: JSX.Element;
  sections: JSX.Element;
  state: JSX.Element;
}

const BasicFilter = ({ sections, poller, state }: BasicFilter): JSX.Element => {
  return (
    <div style={{ maxWidth: 300 }}>
      {sections}
      {poller}
      {state}
    </div>
  );
};

export default BasicFilter;
