import pandas as pd
import glob
import os
import sys
from pathlib import Path

JTL_PATH = "./jmeterFolder/jmeter_results/*.jtl"
SUMMARY_PATH = Path(os.environ.get("GITHUB_STEP_SUMMARY", "jtl_summary.md"))

THRESHOLD_ELAPSED_95TH = 1000  # ms
MAX_ERROR_RATE = 0.01          # 1%

def analyze_jtl(file_path):
    df = pd.read_csv(file_path)

    total_requests = len(df)
    error_count = len(df[df['success'] == False])
    error_rate = error_count / total_requests if total_requests > 0 else 0

    elapsed_90th = df['elapsed'].quantile(0.90)
    elapsed_95th = df['elapsed'].quantile(0.95)
    elapsed_99th = df['elapsed'].quantile(0.99)

    file_name = Path(file_path).name

    summary = f"""
### 📄 File: `{file_name}`

| Metric | Value |
|--------|-------|
| Total Requests | {total_requests} |
| ❌ Error Rate | {error_rate:.2%} |
| ⚡ 90th Percentile | {elapsed_90th:.0f} ms |
| ⚠️ 95th Percentile | {elapsed_95th:.0f} ms |
| 🔥 99th Percentile | {elapsed_99th:.0f} ms |
"""

    if error_rate > MAX_ERROR_RATE:
        summary += "\n🔴 **Test Failed**: Too many failed requests.\n"
        return summary, False

    if elapsed_95th > THRESHOLD_ELAPSED_95TH:
        summary += "\n🔴 **Test Failed**: 95th percentile too high.\n"
        return summary, False

    summary += "\n🟢 **Test Passed**\n"
    return summary, True

all_passed = True
full_report = "## ✅ JMeter Test Summary via Pandas\n\n"

for file in glob.glob(JTL_PATH):
    report, passed = analyze_jtl(file)
    full_report += report + "\n---\n"
    if not passed:
        all_passed = False

SUMMARY_PATH.write_text(full_report)

if not all_passed:
    sys.exit(1)
