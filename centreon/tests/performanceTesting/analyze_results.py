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

    total_requests = len(df)
    error_count = len(df[df['success'] == False])
    error_rate = error_count / total_requests if total_requests > 0 else 0

    elapsed_90th = df['elapsed'].quantile(0.90)
    elapsed_95th = df['elapsed'].quantile(0.95)
    elapsed_99th = df['elapsed'].quantile(0.99)

    file_name = Path(file_path).name

    summary = f"""
### 📄 File: `{file_name}`

| Metric | Value | Description |
|--------|-------|-------------|
| Total Requests | {total_requests} | Total number of HTTP samples in the test |
| {'❌' if error_rate > MAX_ERROR_RATE else '✅'} Error Rate | {error_rate:.2%} | Ratio of failed requests (threshold: {MAX_ERROR_RATE:.0%}) |
| ⚡ 90th Percentile | {elapsed_90th:.0f} ms | 90% of requests were faster than this time |
| ⚠️ 95th Percentile | {elapsed_95th:.0f} ms | 95% of requests were faster (threshold: {THRESHOLD_ELAPSED_95TH} ms) |
| 🔥 99th Percentile | {elapsed_99th:.0f} ms | Tail latency — only 1% of requests took longer |
"""

    if error_rate > MAX_ERROR_RATE:
        summary += "\n🔴 **Test Failed**: Error rate exceeded acceptable threshold.\n"
        summary += "> Too many requests failed. Investigate possible backend issues, timeouts, or bad endpoints.\n"
        return summary, False

    if elapsed_95th > THRESHOLD_ELAPSED_95TH:
        summary += "\n🔴 **Test Failed**: 95th percentile response time too high.\n"
        summary += f"> Performance degraded for top 5% slowest requests. Consider optimizing backend or database queries.\n"
        return summary, False

    summary += "\n🟢 **Test Passed**: All thresholds respected.\n"
    return summary, True

# Final aggregation
all_passed = True
full_report = "## ✅ JMeter Test Summary via Pandas\n"
full_report += "This summary reports key performance indicators from the latest JMeter test run.\n\n"

for file in glob.glob(JTL_PATH):
    report, passed = analyze_jtl(file)
    full_report += report + "\n---\n"
    if not passed:
        all_passed = False

SUMMARY_PATH.write_text(full_report)

if not all_passed:
    sys.exit(1)