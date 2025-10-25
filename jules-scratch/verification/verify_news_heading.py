
from playwright.sync_api import sync_playwright, Page, expect
import os

def run(playwright):
    def test_news_heading(page: Page):
        """
        This test verifies that the heading on the news.html page
        has been changed from 'AI Newsroom' to 'Newsroom'.
        """
        # 1. Arrange: Go to the news.html page.
        page.goto("http://localhost:8000/news.html")

        # 2. Assert: Confirm the heading is correct.
        heading = page.get_by_role("heading", name="Newsroom")
        expect(heading).to_be_visible()

        # 3. Screenshot: Capture the final result for visual verification.
        page.screenshot(path="jules-scratch/verification/verification.png")

    browser = playwright.chromium.launch()
    page = browser.new_page()
    test_news_heading(page)
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
