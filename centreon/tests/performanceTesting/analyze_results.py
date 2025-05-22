import pandas as pd
import glob
import os
import sys
from pathlib import Path

JTL_PATH = "./jmeterFolder/jmeter_results/*.jtl"
SUMMARY_PATH = Path(os.environ.get("GITHUB_STEP_SUMMARY", "jtl_summary.md"))

# Thresholds
THRESHOLD_ELAPSED_95TH = 1000  # ms
MAX_ERROR_RATE = 0.01          # 1%

def analyze_jtl(file_path):
    df = pd.read_csv(file_path)

    required_columns = ['elapsed', 'label', 'responseCode', 'success', 'failureMessage']
    if not all(col in df.columns for col in required_columns):
        raise ValueError(f"Missing expected columns in JTL file: {file_path}")

    total_requests = len(df)
    error_count = len(df[df['success'] == False])
    error_rate = error_count / total_requests if total_requests > 0 else 0
    file_name = Path(file_path).name

    summary = f"\n### 📄 File: `{file_name}`\n"
    summary += "| Label | Total | Errors | Error Rate | 90th | 95th | 99th |\n"
    summary += "|-------|-------|--------|------------|------|------|------|\n"

    all_passed = True
    for label, group in df.groupby("label"):
        total = len(group)
        errors = len(group[group["success"] == False])
        rate = errors / total if total > 0 else 0
        p90 = group["elapsed"].quantile(0.90)
        p95 = group["elapsed"].quantile(0.95)
        p99 = group["elapsed"].quantile(0.99)

        status = "✅"
        if rate > MAX_ERROR_RATE or p95 > THRESHOLD_ELAPSED_95TH:
            status = "❌"
            all_passed = False

        summary += f"| `{label}` | {total} | {errors} | {rate:.2%} | {int(p90)} | {int(p95)} | {int(p99)} |\n"

    # Response code summary
    response_summary = df.groupby(['responseCode', 'success']).size().unstack(fill_value=0)
    summary += "\n#### 📊 Response Code Breakdown:\n"
    summary += response_summary.to_markdown() + "\n"

    # Sample errors
    if not df[df["success"] == False].empty:
        errors_df = df[df["success"] == False][["label", "responseCode", "failureMessage"]].dropna().head(5)
        summary += "\n#### ❗ Sample Errors (first 5):\n"
        summary += errors_df.to_markdown(index=False) + "\n"

    if all_passed:
        summary += "\n🟢 **Test Passed**: All thresholds respected.\n"
    else:
        summary += "\n🔴 **Test Failed**: Thresholds violated.\n"

    return summary, all_passed

# Final aggregation
all_passed = True
full_report = "## ✅ JMeter Test Summary\n\nThis summary reports key performance indicators from the latest JMeter test run.\n\n"

for file in glob.glob(JTL_PATH):
    report, passed = analyze_jtl(file)
    full_report += report + "\n---\n"
    if not passed:
        all_passed = False

SUMMARY_PATH.write_text(full_report)

if not all_passed:
    sys.exit(1)
