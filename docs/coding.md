How to Code the Fanfiction Manager Plugin
This guide explains how to implement the plugin using an AI coding system (e.g., "Cloud Code" with agents). It assumes a multi-agent setup where an orchestrator agent coordinates sub-agents for efficiency, based on best practices from web searches. The main spec is in cloud.md, which summarizes the plugin and points to detailed files. Use it as the entry point to delegate tasks.
Step 1: Understand the Plugin
Read cloud.md first. It provides a concise overview and file references. The plugin is a WordPress plugin—code in PHP, using WP APIs (e.g., register_post_type, add_shortcode, wp_cron). Focus on modularity: Each class in includes/ handles one responsibility (e.g., class-fanfic-shortcodes.php for shortcodes).
Step 2: Setup Multi-Agent System
Use cloud.md to create an orchestrator agent that oversees the process. The orchestrator reads cloud.md, assigns tasks to sub-agents based on phases (from implementation-checklist.md), monitors progress, and handles ambiguities.

Create Sub-Agents: Based on phases/files, spawn specialized sub-agents (e.g., one for data models, one for shortcodes). Limit to 3-5 sub-agents to start simple—add more if needed.

Example: Sub-agent 1: Foundation (data-models.md, user-roles.md).
Sub-agent 2: Frontend (frontend-templates.md, shortcodes.md).
Sub-agent 3: Admin/Features (admin-interface.md, features.md).
Sub-agent 4: Optimization/Testing (performance-optimization.md, accessibility-seo-uiux.md).



The orchestrator delegates: "Sub-agent 1, implement Phase 1 from implementation-checklist.md using data-models.md."
Best Practices for Agents and Sub-Agents (From Web Searches)

Detailed Prompts: Provide comprehensive background from cloud.md/files. E.g., "Using data-models.md, register CPTs with hierarchical=true."
Narrow Scope: Assign sub-agents single responsibilities (e.g., one sub-agent per phase) to avoid complexity.
Structured Output: Require sub-agents to output code in formatted blocks, with comments explaining WP hooks.
Hybrid Approach: Combine AI with traditional code—use AI for generation, manual review for WP-specifics (e.g., security).
Start Simple: Begin with orchestrator + 2 sub-agents; scale as workflow stabilizes.
Context Management: Feed sub-agents only relevant files to avoid token overload.
Safety/Permissions: Limit sub-agent tool access (e.g., no external installs); use nonces in code.
Evaluation: Orchestrator reviews sub-agent outputs, iterates if needed.

Strategies for Orchestrator Agent (From Web Searches)

Sequential/Concurrent Patterns: Use sequential for dependent tasks (e.g., foundation before frontend); concurrent for independent (e.g., shortcodes and admin parallel).
Handoff/Group Chat: Orchestrator hands off to sub-agents; use "group chat" mode if agents need to collaborate (e.g., features sub-agent consults data-models sub-agent).
Central Controller: Orchestrator decides sequence, monitors latency/costs, evaluates outputs.
Guardrails: Set limits on LLM calls; use structured prompts for consistency.
Tools: Leverage Azure/IBM-like patterns if available; otherwise, simple prompt chaining.

Step 3: Use Checkpoints
Implement checkpoints to save states during coding sessions (from searches):

How to Use: Before major changes (e.g., after Phase 1), create a snapshot of code/conversation state. Like git commits or Claude Code checkpoints—e.g., "Checkpoint: Foundation complete, code in fanfiction-manager.php."
In Sessions: Auto-save before AI edits; resume from checkpoint if interrupted.
Benefits: Resume failed sessions, fine-tune without restarting.

Step 4: Resuming Sessions When Tokens Are Finished
Token limits can cut off long sessions (from searches):

Strategies:

Summarize context: At limit, orchestrator summarizes progress (e.g., "Completed Phase 1; next: Phase 2") and starts new session with summary + checkpoint.
Continue Prompt: In new session, prompt "Continue from [summary/checkpoint]."
Retry Mechanism: If failure, retry every 15 min (up to 10 times), then alert user.
Batch Prompts: Break tasks into smaller chunks to stay under limits.
New Chat: If exceeded, start new but copy essential context; avoid by monitoring tokens.



Step 5: Handling Ambiguities
If any agent (orchestrator/sub-agent) encounters ambiguity:

Pause immediately.
Ask user explicitly and simply (user is not a programmer): E.g., "Please clarify: In the ratings system, should half-stars be allowed for individual ratings? Yes or no?"
Do not assume—wait for response before proceeding.
Orchestrator collects questions from sub-agents and asks user.

Overall Coding Strategy

Phased Approach: Follow implementation-checklist.md sequentially.
WP Best Practices: Sanitize inputs, escape outputs, use hooks/filters, test on low-resource servers.
Testing: Unit tests per class; integration for workflows; security audit.
Resume on Failure: Use checkpoints + summaries.
