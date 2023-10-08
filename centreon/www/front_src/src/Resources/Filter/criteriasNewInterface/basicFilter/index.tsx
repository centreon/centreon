interface BasicFilter {
  poller: JSX.Element;
  sections: JSX.Element;
  state: JSX.Element;
}

const BasicFilter = ({ sections, poller, state }: BasicFilter): JSX.Element => {
  return (
    <div>
      {sections}
      {poller}
      {state}
    </div>
  );
};

export default BasicFilter;
