export const generateItems = (count: number) =>
  Array(count)
    .fill(0)
    .map((_, idx) => ({
      id: idx,
      name: `Item Item Item ${idx}`
    }));
