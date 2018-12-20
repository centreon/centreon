import React from "react";
import "./search-live.scss";

const SearchLive = ({ label }) => (
  <div className="search-live">
    <label for="search-live">{label}</label>
    <input type="text" id="search-live" name="search-live" />
  </div>
);

export default SearchLive;
